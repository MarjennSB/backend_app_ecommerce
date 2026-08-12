<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Venta\VentaCollection;
use App\Http\Resources\Venta\VentaResource;
use App\Models\ComprobanteVenta;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\TipoMovimientoInventario;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ApiVentaController extends Controller
{
    // ... aquí irán los métodos

    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_venta')->only('index');
        $this->middleware('can:registrar_venta')->only('store');
        $this->middleware('can:editar_venta')->only('update');
        /* $this->middleware('can:eliminar_venta')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/ventas',
        summary: 'Listar ventas',
        description: 'Obtiene una lista paginada de ventas con la información del usuario, dirección de envío, método de pago, detalles de productos y comprobante asociado. Permite buscar por número o serie de comprobante, código de transacción de pasarela o estado de la venta.',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda por número de comprobante, serie de comprobante, código de transacción de pasarela o estado de la venta.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'F001-00001'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de ventas que se mostrarán por página.',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    minimum: 1,
                    example: 10
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ventas obtenidas correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 422,
                description: 'Parámetros de consulta inválidos'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno del servidor'
            )
        ]
    )]
    
    public function index(Request $request)
    {
        $search = $request->input('search');
        $perPage = $request->input('per_page', 10);

        $ventas = Venta::with([
            'usuario',
            'direccionEnvio',
            'tipoMetodoPago',
            'detalles.producto',
            'comprobanteVenta.tipoDocumentoComprobante',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    // Buscar por número de comprobante
                    $q->whereHas('comprobanteVenta', function ($qComprobante) use ($search) {
                        $qComprobante->where('numero_comprobante', 'ilike', "%{$search}%")
                            ->orWhere('serie_comprobante', 'ilike', "%{$search}%");
                    })

                    // Buscar por código de transacción de la pasarela
                    ->orWhere('codigo_transaccion_pasarela', 'ilike', "%{$search}%")

                    // Buscar por estado de la venta
                    ->orWhere('estado_venta', 'ilike', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'ventas' => VentaCollection::collection($ventas),
            'total' => $ventas->total(),
            'pagination' => [
                'total' => $ventas->total(),
                'current_page' => $ventas->currentPage(),
                'last_page' => $ventas->lastPage(),
                'per_page' => $ventas->perPage(),
                'total_visible' => min($ventas->lastPage(), 5),
                'itemsPerPage' => $ventas->perPage(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/ventas/{id}',
        summary: 'Obtener detalle de una venta por id',
        description: 'Obtiene la información completa de una venta (protegido, requiere autenticación).',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la venta',
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Venta encontrada'),
            new OA\Response(response: 404, description: 'Venta no encontrada'),
            new OA\Response(response: 401, description: 'No autorizado')
        ]
    )]
    public function show(Venta $venta)
    {
        // Note: routes are protected by jwt.verify; further policies can be applied as needed
        $venta->load([
            'usuario.persona',
            'direccionEnvio',
            'tipoMetodoPago',
            'detalles.producto',
            'comprobanteVenta.tipoDocumentoComprobante',
        ]);

        return VentaResource::make($venta);
    }

    #[OA\Post(
        path: '/api/ventas',
        summary: 'Registrar nueva venta',
        description: 'Registra una nueva venta para el usuario autenticado. Valida el stock disponible, crea la venta, sus detalles y comprobante, descuenta el stock de los productos, registra el movimiento en el Kardex y genera la transacción financiera asociada.',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'direccion_envio_id',
                        'tipo_metodo_pago_id',
                        'tipo_documento_comprobante_id',
                        'serie_comprobante',
                        'numero_comprobante',
                        'fecha_venta',
                        'costo_envio',
                        'impuestos_igv',
                        'estado',
                        'detalles',
                    ],
                    properties: [
                        new OA\Property(
                            property: 'direccion_envio_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID de la dirección de envío del usuario autenticado.'
                        ),

                        new OA\Property(
                            property: 'tipo_metodo_pago_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del método de pago. Ejemplo: Efectivo, Tarjeta, Yape, Plin, etc.'
                        ),

                        new OA\Property(
                            property: 'tipo_documento_comprobante_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del tipo de documento del comprobante. Ejemplo: Factura o Boleta.'
                        ),

                        new OA\Property(
                            property: 'serie_comprobante',
                            type: 'string',
                            maxLength: 10,
                            example: 'B001',
                            description: 'Serie del comprobante de venta.'
                        ),

                        new OA\Property(
                            property: 'numero_comprobante',
                            type: 'string',
                            maxLength: 20,
                            example: '00000456',
                            description: 'Número correlativo del comprobante.'
                        ),

                        new OA\Property(
                            property: 'codigo_transaccion_pasarela',
                            type: 'string',
                            maxLength: 150,
                            nullable: true,
                            example: 'TXN-20260811-00001',
                            description: 'Código de transacción generado por la pasarela de pago, si corresponde.'
                        ),

                        new OA\Property(
                            property: 'fecha_venta',
                            type: 'string',
                            format: 'date',
                            example: '2026-08-11',
                            description: 'Fecha en la que se realizó la venta.'
                        ),

                        new OA\Property(
                            property: 'costo_envio',
                            type: 'number',
                            format: 'float',
                            example: 15.00,
                            description: 'Costo de envío de la venta.'
                        ),

                        new OA\Property(
                            property: 'descuento_total',
                            type: 'number',
                            format: 'float',
                            nullable: true,
                            default: 0,
                            example: 10.00,
                            description: 'Descuento total aplicado a la venta.'
                        ),

                        new OA\Property(
                            property: 'impuestos_igv',
                            type: 'number',
                            format: 'float',
                            example: 271.80,
                            description: 'Monto correspondiente al IGV de la venta.'
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado del registro: 1 activo, 0 inactivo.'
                        ),

                        new OA\Property(
                            property: 'archivo_pdf_xml',
                            type: 'string',
                            format: 'binary',
                            nullable: true,
                            description: 'Archivo PDF o XML asociado al comprobante. Opcional. Máximo 5MB.'
                        ),

                        new OA\Property(
                            property: 'detalles',
                            type: 'array',
                            minItems: 1,
                            description: 'Lista de productos incluidos en la venta.',
                            items: new OA\Items(
                                type: 'object',
                                required: [
                                    'producto_id',
                                    'cantidad',
                                    'precio_unitario',
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'producto_id',
                                        type: 'integer',
                                        example: 1,
                                        description: 'ID del producto.'
                                    ),

                                    new OA\Property(
                                        property: 'cantidad',
                                        type: 'integer',
                                        minimum: 1,
                                        example: 2,
                                        description: 'Cantidad de unidades a vender.'
                                    ),

                                    new OA\Property(
                                        property: 'precio_unitario',
                                        type: 'number',
                                        format: 'float',
                                        minimum: 0,
                                        example: 150.00,
                                        description: 'Precio unitario del producto.'
                                    ),

                                    new OA\Property(
                                        property: 'porcentaje_descuento',
                                        type: 'number',
                                        format: 'float',
                                        minimum: 0,
                                        maximum: 100,
                                        nullable: true,
                                        default: 0,
                                        example: 5.00,
                                        description: 'Porcentaje de descuento aplicado al producto.'
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Venta registrada correctamente'
            ),

            new OA\Response(
                response: 422,
                description: 'Errores de validación o stock insuficiente'
            ),

            new OA\Response(
                response: 500,
                description: 'Error interno al registrar la venta'
            )
        ]
    )]
    public function store(Request $request)
    {
        try {
            $request->validate([
                'direccion_envio_id'            => ['required', 'integer', 'exists:direcciones_envio,id'],
                'tipo_metodo_pago_id'           => ['required', 'integer', 'exists:tipo_metodo_pagos,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'serie_comprobante'             => ['required', 'string', 'max:10'],
                'numero_comprobante'            => ['required', 'string', 'max:20'],
                'fecha_venta'                   => ['required', 'date'],
                'costo_envio'                   => ['required', 'numeric', 'min:0'],
                'descuento_total'               => ['nullable', 'numeric', 'min:0'],
                'impuestos_igv'                 => ['required', 'numeric', 'min:0'],
                'estado'                        => ['required', 'boolean'],
                'archivo_pdf_xml'               => ['nullable', 'file', 'mimes:pdf,xml', 'max:5120'],

                'detalles'                      => ['required', 'array', 'min:1'],
                'detalles.*.producto_id'        => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad'           => ['required', 'integer', 'min:1'],
                'detalles.*.precio_unitario'    => ['required', 'numeric', 'min:0'],
                'detalles.*.porcentaje_descuento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ], [
                'direccion_envio_id.required' => 'La dirección de envío es obligatoria.',
                'direccion_envio_id.exists' => 'La dirección de envío seleccionada no existe.',
                'tipo_metodo_pago_id.required' => 'El método de pago es obligatorio.',
                'tipo_metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
                'tipo_documento_comprobante_id.required' => 'El tipo de documento es obligatorio.',
                'tipo_documento_comprobante_id.exists' => 'El tipo de documento seleccionado no existe.',
                'serie_comprobante.required' => 'La serie del comprobante es obligatoria.',
                'numero_comprobante.required' => 'El número del comprobante es obligatorio.',
                'fecha_venta.required' => 'La fecha de venta es obligatoria.',
                'costo_envio.required' => 'El costo de envío es obligatorio.',
                'impuestos_igv.required' => 'El IGV es obligatorio.',
                'detalles.required' => 'Los detalles de la venta son obligatorios.',
                'detalles.array' => 'Los detalles deben enviarse como un arreglo.',
                'detalles.min' => 'La venta debe contener al menos un producto.',
                'detalles.*.producto_id.required' => 'El producto es obligatorio.',
                'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
                'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
                'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
                'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        /*
        |--------------------------------------------------------------------------
        | Validar stock antes de iniciar la transacción
        |--------------------------------------------------------------------------
        */

        $erroresStock = [];

        foreach ($request->detalles as $index => $item) {
            $producto = Producto::find($item['producto_id']);

            if ($producto && $producto->cantidad < $item['cantidad']) {
                $erroresStock["detalles.{$index}.cantidad"] = [
                    "Stock insuficiente para '{$producto->nombre}'. Disponible: {$producto->cantidad}."
                ];
            }
        }

        if (!empty($erroresStock)) {
            return response()->json([
                'mensaje' => 'No hay stock suficiente para uno o más productos.',
                'errors' => $erroresStock,
            ], 422);
        }

        DB::beginTransaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Calcular totales
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;

            foreach ($request->detalles as $item) {
                $cantidad = $item['cantidad'];
                $precioUnitario = $item['precio_unitario'];
                $porcentajeDescuento = $item['porcentaje_descuento'] ?? 0;

                $subtotalProducto = $cantidad * $precioUnitario;

                $descuentoProducto = $subtotalProducto * ($porcentajeDescuento / 100);

                $subtotal += $subtotalProducto - $descuentoProducto;
            }

            $descuentoTotal = $request->descuento_total ?? 0;
            $costoEnvio = $request->costo_envio;
            $igv = $request->impuestos_igv;

            $montoTotal = $subtotal - $descuentoTotal + $costoEnvio + $igv;

            if ($montoTotal < 0) {
                throw new \RuntimeException(
                    'El monto total de la venta no puede ser negativo.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Crear venta
            |--------------------------------------------------------------------------
            */

            $venta = new Venta();

            $venta->usuario_id = Auth::id();
            $venta->direccion_envio_id = $request->direccion_envio_id;
            $venta->tipo_metodo_pago_id = $request->tipo_metodo_pago_id;
            $venta->codigo_transaccion_pasarela = $request->codigo_transaccion_pasarela ?? null;
            $venta->subtotal = $subtotal;
            $venta->descuento_total = $descuentoTotal;
            $venta->costo_envio = $costoEnvio;
            $venta->impuestos_igv = $igv;
            $venta->monto_total = $montoTotal;
            $venta->estado_venta = 'PENDIENTE';
            $venta->fecha_venta = $request->fecha_venta;
            $venta->estado = $request->estado;

            $venta->save();

            /*
            |--------------------------------------------------------------------------
            | Crear comprobante
            |--------------------------------------------------------------------------
            */

            $rutaPdfXml = null;

            if ($request->hasFile('archivo_pdf_xml')) {
                $rutaPdfXml = $request->file('archivo_pdf_xml')
                    ->store('ventas/comprobantes', 'public');
            }

            $comprobante = new ComprobanteVenta();

            $comprobante->venta_id = $venta->id;
            $comprobante->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $comprobante->serie_comprobante = $request->serie_comprobante;
            $comprobante->numero_comprobante = $request->numero_comprobante;
            $comprobante->ruta_pdf_xml = $rutaPdfXml;
            $comprobante->estado_comprobante = 'EMITIDO';
            $comprobante->fecha_emision = now();
            $comprobante->estado = 1;

            $comprobante->save();

            /*
            |--------------------------------------------------------------------------
            | Obtener tipos de movimiento y transacción
            |--------------------------------------------------------------------------
            */

            $tipoMovVenta = TipoMovimientoInventario::where(
                'siglas',
                'VENTA'
            )->value('id');

            if (empty($tipoMovVenta)) {
                throw new \RuntimeException(
                    'No existe el tipo de movimiento VENTA en la tabla tipo_movimiento_inventarios.'
                );
            }

            $tipoTransaccionVenta = TipoTransaccion::where(
                'siglas',
                'VEN'
            )->value('id');

            if (empty($tipoTransaccionVenta)) {
                throw new \RuntimeException(
                    'No existe el tipo de transacción VEN en la tabla tipo_transacciones.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Crear detalles, descontar stock y registrar Kardex
            |--------------------------------------------------------------------------
            */

            foreach ($request->detalles as $item) {

                $cantidad = $item['cantidad'];
                $precioUnitario = $item['precio_unitario'];
                $porcentajeDescuento = $item['porcentaje_descuento'] ?? 0;

                $subtotalBruto = $cantidad * $precioUnitario;

                $descuentoProducto =
                    $subtotalBruto * ($porcentajeDescuento / 100);

                $subtotalDetalle =
                    $subtotalBruto - $descuentoProducto;

                /*
                | Detalle de venta
                */

                $detalle = new DetalleVenta();

                $detalle->venta_id = $venta->id;
                $detalle->producto_id = $item['producto_id'];
                $detalle->cantidad = $cantidad;
                $detalle->precio_unitario = $precioUnitario;
                $detalle->porcentaje_descuento = $porcentajeDescuento;
                $detalle->subtotal = $subtotalDetalle;
                $detalle->estado = 1;

                $detalle->save();

                /*
                | Descontar stock
                */

                $producto = Producto::find($item['producto_id']);

                $producto->decrement(
                    'cantidad',
                    $cantidad
                );

                /*
                | Kardex
                */

                $movimiento = new Inventario();

                $movimiento->producto_id = $producto->id;
                $movimiento->tipo_movimiento_inventario_id = $tipoMovVenta;
                $movimiento->cantidad = $cantidad;
                $movimiento->tipo_referencia = 'Venta';
                $movimiento->referencia_id = $venta->id;
                $movimiento->motivo =
                    "Salida por venta (Comprobante: {$request->serie_comprobante}-{$request->numero_comprobante})";
                $movimiento->usuario_id = Auth::id();
                $movimiento->estado = 1;

                $movimiento->save();
            }

            /*
            |--------------------------------------------------------------------------
            | Registrar transacción financiera
            |--------------------------------------------------------------------------
            */

            $transaccion = new Transaccion();

            $transaccion->tipo_transaccion_id = $tipoTransaccionVenta;
            $transaccion->tipo_referencia = 'Venta';
            $transaccion->referencia_id = $venta->id;
            $transaccion->monto = $montoTotal;
            $transaccion->motivo =
                "Ingreso por venta (Comprobante: {$request->serie_comprobante}-{$request->numero_comprobante})";
            $transaccion->usuario_id = Auth::id();
            $transaccion->estado = 1;

            $transaccion->save();

            DB::commit();

            /*
            |--------------------------------------------------------------------------
            | Response
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Venta registrada correctamente',
                'venta' => VentaResource::make(
                    $venta->load([
                        'usuario.persona',
                        'direccionEnvio',
                        'tipoMetodoPago',
                        'detalles.producto',
                        'comprobanteVenta.tipoDocumentoComprobante',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error crítico al registrar la venta.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/ventas/{id}',
        summary: 'Actualizar venta y detalles',
        description: 'Actualiza una venta y sus detalles. Recalcula automáticamente el precio total, valida el stock disponible antes de modificar la información, ajusta el inventario y registra los movimientos correspondientes en el Kardex. Permite agregar, modificar o eliminar detalles y reemplazar opcionalmente el comprobante PDF. Usa POST con _method=PUT.',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la venta que se desea actualizar.',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'cliente_id',
                        'tipo_documento_comprobante_id',
                        'tipo_metodo_pago_id',
                        'numero_comprobante',
                        'fecha_venta',
                        'estado',
                        'detalles'
                    ],
                    properties: [
                        new OA\Property(
                            property: '_method',
                            type: 'string',
                            example: 'PUT',
                            description: 'Indica a Laravel que la petición corresponde a una actualización.'
                        ),

                        new OA\Property(
                            property: 'cliente_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del cliente asociado a la venta.'
                        ),

                        new OA\Property(
                            property: 'tipo_documento_comprobante_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del tipo de documento del comprobante.'
                        ),

                        new OA\Property(
                            property: 'tipo_metodo_pago_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID del método de pago utilizado.'
                        ),

                        new OA\Property(
                            property: 'numero_comprobante',
                            type: 'string',
                            maxLength: 20,
                            example: 'B001-000456',
                            description: 'Número del comprobante de la venta.'
                        ),

                        new OA\Property(
                            property: 'fecha_venta',
                            type: 'string',
                            format: 'date',
                            example: '2026-08-11',
                            description: 'Fecha en que se realizó la venta.'
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado de la venta: 1 activa, 0 inactiva.'
                        ),

                        new OA\Property(
                            property: 'archivo_pdf',
                            type: 'string',
                            format: 'binary',
                            description: 'Nuevo comprobante PDF. Si se envía, reemplaza el archivo anterior. Máximo 5MB.'
                        ),

                        new OA\Property(
                            property: 'detalles[0][id]',
                            type: 'integer',
                            nullable: true,
                            example: 1,
                            description: 'ID del detalle existente. Omitir este campo cuando se trate de un detalle nuevo.'
                        ),

                        new OA\Property(
                            property: 'detalles[0][producto_id]',
                            type: 'integer',
                            example: 1,
                            description: 'ID del producto.'
                        ),

                        new OA\Property(
                            property: 'detalles[0][cantidad]',
                            type: 'integer',
                            minimum: 1,
                            example: 3,
                            description: 'Cantidad solicitada del producto.'
                        ),

                        new OA\Property(
                            property: 'detalles[0][precio_unitario]',
                            type: 'number',
                            format: 'float',
                            minimum: 0,
                            example: 150.00,
                            description: 'Precio unitario del producto.'
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Venta actualizada correctamente'
            ),
            new OA\Response(
                response: 404,
                description: 'Venta no encontrada'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación o stock insuficiente'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al actualizar la venta'
            )
        ]
    )]



    public function update(Request $request, Venta $venta)
    {
        try {
            $request->validate([
                'direccion_envio_id' => ['required', 'integer', 'exists:direcciones_envio,id'],
                'tipo_metodo_pago_id' => ['required', 'integer', 'exists:tipo_metodo_pagos,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'serie_comprobante' => ['required', 'string', 'max:10'],
                'numero_comprobante' => ['required', 'string', 'max:20'],
                'codigo_transaccion_pasarela' => ['nullable', 'string', 'max:150'],
                'fecha_venta' => ['required', 'date'],
                'costo_envio' => ['required', 'numeric', 'min:0'],
                'descuento_total' => ['nullable', 'numeric', 'min:0'],
                'impuestos_igv' => ['required', 'numeric', 'min:0'],
                'estado_venta' => ['required', 'string', 'max:30'],
                'estado' => ['required', 'boolean'],
                'archivo_pdf_xml' => ['nullable', 'file', 'mimes:pdf,xml', 'max:5120'],

                'detalles' => ['required', 'array', 'min:1'],
                'detalles.*.id' => ['nullable', 'integer', 'exists:detalle_ventas,id'],
                'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
                'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
                'detalles.*.porcentaje_descuento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            ], [
                'direccion_envio_id.required' => 'La dirección de envío es obligatoria.',
                'direccion_envio_id.exists' => 'La dirección de envío seleccionada no existe.',
                'tipo_metodo_pago_id.required' => 'El método de pago es obligatorio.',
                'tipo_metodo_pago_id.exists' => 'El método de pago seleccionado no existe.',
                'tipo_documento_comprobante_id.required' => 'El tipo de documento es obligatorio.',
                'tipo_documento_comprobante_id.exists' => 'El tipo de documento seleccionado no existe.',
                'serie_comprobante.required' => 'La serie del comprobante es obligatoria.',
                'numero_comprobante.required' => 'El número del comprobante es obligatorio.',
                'fecha_venta.required' => 'La fecha de venta es obligatoria.',
                'costo_envio.required' => 'El costo de envío es obligatorio.',
                'impuestos_igv.required' => 'El IGV es obligatorio.',
                'estado_venta.required' => 'El estado de la venta es obligatorio.',
                'detalles.required' => 'Los detalles de la venta son obligatorios.',
                'detalles.array' => 'Los detalles deben enviarse como un arreglo.',
                'detalles.min' => 'La venta debe contener al menos un producto.',
                'detalles.*.producto_id.required' => 'El producto es obligatorio.',
                'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
                'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
                'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
                'detalles.*.precio_unitario.required' => 'El precio unitario es obligatorio.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            /*
            |--------------------------------------------------------------------------
            | Obtener detalles actuales de la venta
            |--------------------------------------------------------------------------
            */

            $detallesActuales = DetalleVenta::where('venta_id', $venta->id)
                ->get()
                ->keyBy('id');

            /*
            |--------------------------------------------------------------------------
            | Validar que los detalles enviados pertenezcan a esta venta
            |--------------------------------------------------------------------------
            */

            foreach ($request->detalles as $item) {
                if (!empty($item['id'])) {
                    $detalleActual = $detallesActuales->get($item['id']);

                    if (!$detalleActual) {
                        throw new \RuntimeException(
                            "El detalle {$item['id']} no pertenece a la venta {$venta->id}."
                        );
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Validación de stock para la edición
            |--------------------------------------------------------------------------
            |
            | Primero devolvemos mentalmente al stock las cantidades actuales
            | de la venta y después verificamos las nuevas cantidades.
            |
            */

            $stockNecesario = [];

            foreach ($request->detalles as $item) {
                $productoId = $item['producto_id'];

                if (!isset($stockNecesario[$productoId])) {
                    $stockNecesario[$productoId] = 0;
                }

                $stockNecesario[$productoId] += $item['cantidad'];
            }

            /*
            |--------------------------------------------------------------------------
            | Stock disponible real para la edición
            |--------------------------------------------------------------------------
            */

            $stockDisponible = [];

            foreach ($detallesActuales as $detalleActual) {
                if (!isset($stockDisponible[$detalleActual->producto_id])) {
                    $stockDisponible[$detalleActual->producto_id] = 0;
                }

                $stockDisponible[$detalleActual->producto_id] += $detalleActual->cantidad;
            }

            foreach ($stockNecesario as $productoId => $cantidadNueva) {
                $producto = Producto::find($productoId);

                $cantidadDisponible =
                    $producto->cantidad +
                    ($stockDisponible[$productoId] ?? 0);

                if ($cantidadDisponible < $cantidadNueva) {
                    throw new \RuntimeException(
                        "Stock insuficiente para '{$producto->nombre}'. " .
                        "Disponible para esta edición: {$cantidadDisponible}."
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Actualizar archivo del comprobante
            |--------------------------------------------------------------------------
            */

            $comprobante = ComprobanteVenta::where(
                'venta_id',
                $venta->id
            )->first();

            if (!$comprobante) {
                throw new \RuntimeException(
                    'La venta no tiene un comprobante asociado.'
                );
            }

            if ($request->hasFile('archivo_pdf_xml')) {
                if (
                    $comprobante->ruta_pdf_xml &&
                    \Illuminate\Support\Facades\Storage::disk('public')
                        ->exists($comprobante->ruta_pdf_xml)
                ) {
                    \Illuminate\Support\Facades\Storage::disk('public')
                        ->delete($comprobante->ruta_pdf_xml);
                }

                $comprobante->ruta_pdf_xml = $request
                    ->file('archivo_pdf_xml')
                    ->store('ventas/comprobantes', 'public');
            }

            /*
            |--------------------------------------------------------------------------
            | Recalcular subtotal
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;

            foreach ($request->detalles as $item) {
                $cantidad = $item['cantidad'];
                $precioUnitario = $item['precio_unitario'];
                $porcentajeDescuento = $item['porcentaje_descuento'] ?? 0;

                $subtotalBruto = $cantidad * $precioUnitario;

                $descuentoProducto =
                    $subtotalBruto * ($porcentajeDescuento / 100);

                $subtotal += $subtotalBruto - $descuentoProducto;
            }

            $descuentoTotal = $request->descuento_total ?? 0;
            $costoEnvio = $request->costo_envio;
            $igv = $request->impuestos_igv;

            $montoTotal = $subtotal - $descuentoTotal +$costoEnvio + $igv;

            if ($montoTotal < 0) {
                throw new \RuntimeException(
                    'El monto total de la venta no puede ser negativo.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Actualizar venta
            |--------------------------------------------------------------------------
            */

            $venta->direccion_envio_id = $request->direccion_envio_id;
            $venta->tipo_metodo_pago_id = $request->tipo_metodo_pago_id;
            $venta->codigo_transaccion_pasarela =
                $request->codigo_transaccion_pasarela ?? null;
            $venta->subtotal = $subtotal;
            $venta->descuento_total = $descuentoTotal;
            $venta->costo_envio = $costoEnvio;
            $venta->impuestos_igv = $igv;
            $venta->monto_total = $montoTotal;
            $venta->estado_venta = $request->estado_venta;
            $venta->fecha_venta = $request->fecha_venta;
            $venta->estado = $request->estado;

            $venta->save();

            /*
            |--------------------------------------------------------------------------
            | Actualizar comprobante
            |--------------------------------------------------------------------------
            */

            $comprobante->tipo_documento_comprobante_id =
                $request->tipo_documento_comprobante_id;

            $comprobante->serie_comprobante =
                $request->serie_comprobante;

            $comprobante->numero_comprobante =
                $request->numero_comprobante;

            $comprobante->save();

            /*
            |--------------------------------------------------------------------------
            | Obtener tipos de movimiento
            |--------------------------------------------------------------------------
            */

            $tipoMovVenta = TipoMovimientoInventario::where(
                'siglas',
                'VENTA'
            )->value('id');

            $tipoMovAjuste = TipoMovimientoInventario::where(
                'siglas',
                'AJUSTE'
            )->value('id');

            if (empty($tipoMovVenta)) {
                throw new \RuntimeException(
                    'No existe el tipo de movimiento VENTA.'
                );
            }

            if (empty($tipoMovAjuste)) {
                throw new \RuntimeException(
                    'No existe el tipo de movimiento AJUSTE.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Detalles eliminados
            |--------------------------------------------------------------------------
            */

            $idsEntrantes = collect($request->detalles)
                ->pluck('id')
                ->filter()
                ->toArray();

            $detallesAEliminar = $detallesActuales
                ->filter(function ($detalle) use ($idsEntrantes) {
                    return !in_array($detalle->id, $idsEntrantes);
                });

            foreach ($detallesAEliminar as $detalleViejo) {

                /*
                | Devolver stock
                */

                Producto::find($detalleViejo->producto_id)
                    ->increment('cantidad', $detalleViejo->cantidad);

                /*
                | Kardex: ajuste positivo
                */

                $movimiento = new Inventario();

                $movimiento->producto_id =
                    $detalleViejo->producto_id;

                $movimiento->tipo_movimiento_inventario_id =
                    $tipoMovAjuste;

                $movimiento->cantidad =
                    $detalleViejo->cantidad;

                $movimiento->tipo_referencia = 'Venta';
                $movimiento->referencia_id = $venta->id;

                $movimiento->motivo =
                    'Ajuste positivo: producto devuelto tras eliminación en edición de venta';

                $movimiento->usuario_id = Auth::id();
                $movimiento->estado = 1;

                $movimiento->save();

                /*
                | Eliminar detalle
                */

                $detalleViejo->delete();
            }

            /*
            |--------------------------------------------------------------------------
            | Actualizar y crear detalles
            |--------------------------------------------------------------------------
            */

            foreach ($request->detalles as $item) {

                $cantidadNueva = $item['cantidad'];
                $precioUnitario = $item['precio_unitario'];
                $porcentajeDescuento =
                    $item['porcentaje_descuento'] ?? 0;

                $subtotalBruto =
                    $cantidadNueva * $precioUnitario;

                $descuentoProducto =
                    $subtotalBruto *
                    ($porcentajeDescuento / 100);

                $subtotalDetalle =
                    $subtotalBruto -
                    $descuentoProducto;

                /*
                |--------------------------------------------------------------------------
                | Detalle existente
                |--------------------------------------------------------------------------
                */

                if (!empty($item['id'])) {

                    $detalle = $detallesActuales->get($item['id']);

                    $productoAnterior = $detalle->producto_id;
                    $cantidadAnterior = $detalle->cantidad;

                    $productoCambio =
                        $productoAnterior != $item['producto_id'];

                    $cantidadCambio =
                        $cantidadAnterior != $cantidadNueva;

                    if ($productoCambio || $cantidadCambio) {

                        /*
                        | Devolver stock anterior
                        */

                        Producto::find($productoAnterior)
                            ->increment(
                                'cantidad',
                                $cantidadAnterior
                            );

                        /*
                        | Kardex ajuste positivo
                        */

                        $movAjuste = new Inventario();

                        $movAjuste->producto_id =
                            $productoAnterior;

                        $movAjuste->tipo_movimiento_inventario_id =
                            $tipoMovAjuste;

                        $movAjuste->cantidad =
                            $cantidadAnterior;

                        $movAjuste->tipo_referencia = 'Venta';
                        $movAjuste->referencia_id = $venta->id;

                        $movAjuste->motivo =
                            'Ajuste positivo por reversión previa a edición de venta';

                        $movAjuste->usuario_id = Auth::id();
                        $movAjuste->estado = 1;

                        $movAjuste->save();

                        /*
                        | Restar nuevo stock
                        */

                        Producto::find($item['producto_id'])
                            ->decrement(
                                'cantidad',
                                $cantidadNueva
                            );

                        /*
                        | Kardex nueva salida
                        */

                        $movVenta = new Inventario();

                        $movVenta->producto_id =
                            $item['producto_id'];

                        $movVenta->tipo_movimiento_inventario_id =
                            $tipoMovVenta;

                        $movVenta->cantidad =
                            $cantidadNueva;

                        $movVenta->tipo_referencia = 'Venta';
                        $movVenta->referencia_id = $venta->id;

                        $movVenta->motivo =
                            'Salida corregida por edición de venta';

                        $movVenta->usuario_id = Auth::id();
                        $movVenta->estado = 1;

                        $movVenta->save();
                    }

                    /*
                    | Actualizar detalle
                    */

                    $detalle->producto_id =
                        $item['producto_id'];

                    $detalle->cantidad =
                        $cantidadNueva;

                    $detalle->precio_unitario =
                        $precioUnitario;

                    $detalle->porcentaje_descuento =
                        $porcentajeDescuento;

                    $detalle->subtotal =
                        $subtotalDetalle;

                    $detalle->save();

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Crear nuevo detalle
                    |--------------------------------------------------------------------------
                    */

                    $detalle = new DetalleVenta();

                    $detalle->venta_id = $venta->id;
                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $cantidadNueva;
                    $detalle->precio_unitario = $precioUnitario;
                    $detalle->porcentaje_descuento =
                        $porcentajeDescuento;
                    $detalle->subtotal = $subtotalDetalle;
                    $detalle->estado = 1;

                    $detalle->save();

                    /*
                    | Restar stock
                    */

                    Producto::find($item['producto_id'])
                        ->decrement(
                            'cantidad',
                            $cantidadNueva
                        );

                    /*
                    | Kardex
                    */

                    $movNuevo = new Inventario();

                    $movNuevo->producto_id =
                        $item['producto_id'];

                    $movNuevo->tipo_movimiento_inventario_id =
                        $tipoMovVenta;

                    $movNuevo->cantidad =
                        $cantidadNueva;

                    $movNuevo->tipo_referencia = 'Venta';
                    $movNuevo->referencia_id = $venta->id;

                    $movNuevo->motivo =
                        'Salida por producto agregado durante la edición de venta';

                    $movNuevo->usuario_id = Auth::id();
                    $movNuevo->estado = 1;

                    $movNuevo->save();
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Actualizar transacción financiera
            |--------------------------------------------------------------------------
            */

            $transaccion = Transaccion::where(
                'tipo_referencia',
                'Venta'
            )
                ->where(
                    'referencia_id',
                    $venta->id
                )
                ->first();

            if ($transaccion) {

                $transaccion->monto =
                    $venta->monto_total;

                $transaccion->motivo =
                    "Ingreso por venta editada (Comprobante: " .
                    $comprobante->serie_comprobante .
                    '-' .
                    $comprobante->numero_comprobante .
                    ")";

                $transaccion->save();

            } else {

                $tipoTransaccionVenta = TipoTransaccion::where(
                    'siglas',
                    'VEN'
                )->value('id');

                if (empty($tipoTransaccionVenta)) {
                    throw new \RuntimeException(
                        'No existe el tipo de transacción VEN.'
                    );
                }

                $transaccion = new Transaccion();

                $transaccion->tipo_transaccion_id =
                    $tipoTransaccionVenta;

                $transaccion->tipo_referencia = 'Venta';
                $transaccion->referencia_id = $venta->id;
                $transaccion->monto = $venta->monto_total;

                $transaccion->motivo =
                    "Ingreso por venta editada (Comprobante: " .
                    $comprobante->serie_comprobante .
                    '-' .
                    $comprobante->numero_comprobante .
                    ")";

                $transaccion->usuario_id = Auth::id();
                $transaccion->estado = 1;

                $transaccion->save();
            }

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Venta actualizada correctamente',
                'venta' => VentaResource::make(
                    $venta->load([
                        'usuario.persona',
                        'direccionEnvio',
                        'tipoMetodoPago',
                        'detalles.producto',
                        'comprobanteVenta.tipoDocumentoComprobante',
                    ])
                ),
            ], 200);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error crítico al actualizar la venta.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}