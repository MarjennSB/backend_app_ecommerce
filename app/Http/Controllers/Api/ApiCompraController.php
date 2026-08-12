<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Compra\CompraResource;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\TipoMovimientoInventario;
use App\Models\TipoTransaccion;
use App\Models\Transaccion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;
use Illuminate\Support\Facades\Auth;

class ApiCompraController extends Controller
{

    public function __construct()
        {
            $this->middleware('jwt.auth');
            $this->middleware('can:listar_compra')->only('index');
            $this->middleware('can:registrar_compra')->only('store');
            $this->middleware('can:editar_compra')->only('update');
            /* $this->middleware('can:eliminar_compra')->only('destroy'); */
        }

    #[OA\Get(
        path: '/api/compras',
        summary: 'Listar compras',
        description: 'Obtiene una lista paginada de compras. Permite buscar por número de comprobante, datos del proveedor o información de los productos incluidos en los detalles de la compra.',
        tags: ['Compras'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda por número de comprobante, nombre, apellido o documento del proveedor, nombre, slug o código de barras del producto.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'laptop'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página.',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    example: 15
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de compras obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            )
        ]
    )]

    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $compras = Compra::with([
            'proveedor.persona',
            'usuario.persona',
            'tipoDocumentoComprobante',
            'detalles.producto',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    $q->where('numero_comprobante', 'ilike', "%{$search}%")
                        ->orWhereHas('proveedor.persona', function ($personaQuery) use ($search) {
                            $personaQuery->where('nombres', 'ilike', "%{$search}%")
                                ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                                ->orWhere('numero_documento', 'ilike', "%{$search}%");
                        })
                        ->orWhereHas('detalles.producto', function ($productoQuery) use ($search) {
                            $productoQuery->where('nombre', 'ilike', "%{$search}%")
                                ->orWhere('slug', 'ilike', "%{$search}%")
                                ->orWhere('codigo_barras', 'ilike', "%{$search}%");
                        });
                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'compras' => CompraResource::collection($compras),
            'total' => $compras->total(),
            'pagination' => [
                'total' => $compras->total(),
                'current_page' => $compras->currentPage(),
                'last_page' => $compras->lastPage(),
                'per_page' => $compras->perPage(),
                'total_visible' => min($compras->lastPage(), 5),
                'itemsPerPage' => $compras->perPage(),
            ],
        ]);
    }


    #[OA\Post(
        path: '/api/compras',
        summary: 'Registrar compra',
        description: 'Registra una nueva compra con sus detalles. Calcula automáticamente el costo total, actualiza el stock de los productos, registra los movimientos en el Kardex y genera una única transacción de caja asociada a la compra. Permite adjuntar opcionalmente el comprobante en formato PDF.',
        tags: ['Compras'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [
                        'proveedor_id',
                        'tipo_documento_comprobante_id',
                        'numero_comprobante',
                        'fecha_compra',
                        'estado',
                        'detalles[0][producto_id]',
                        'detalles[0][cantidad]',
                        'detalles[0][costo_unitario]'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'proveedor_id',
                            type: 'integer',
                            description: 'ID del proveedor de la compra.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'tipo_documento_comprobante_id',
                            type: 'integer',
                            description: 'ID del tipo de documento del comprobante.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'numero_comprobante',
                            type: 'string',
                            maxLength: 50,
                            description: 'Número del comprobante de compra.',
                            example: 'F001-00001234'
                        ),
                        new OA\Property(
                            property: 'fecha_compra',
                            type: 'string',
                            format: 'date',
                            description: 'Fecha en que se realizó la compra.',
                            example: '2026-08-11'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            description: 'Estado de la compra: 1 activo, 0 inactivo.',
                            enum: [0, 1],
                            example: 1
                        ),
                        new OA\Property(
                            property: 'archivo_pdf',
                            type: 'string',
                            format: 'binary',
                            description: 'Comprobante de compra en PDF. Máximo 5MB.'
                        ),

                        // DETALLE 1
                        new OA\Property(
                            property: 'detalles[0][producto_id]',
                            type: 'integer',
                            description: 'ID del primer producto.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'detalles[0][cantidad]',
                            type: 'integer',
                            description: 'Cantidad del primer producto.',
                            minimum: 1,
                            example: 10
                        ),
                        new OA\Property(
                            property: 'detalles[0][costo_unitario]',
                            type: 'number',
                            format: 'float',
                            description: 'Costo unitario del primer producto.',
                            minimum: 0,
                            example: 150.50
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Compra registrada correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error crítico al registrar la compra'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {
            $request->validate([
                'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'numero_comprobante' => ['required', 'string', 'max:50'],
                'fecha_compra' => ['required', 'date'],
                'estado' => ['required', 'boolean'],
                'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'detalles' => ['required', 'array', 'min:1'],
                'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
                'detalles.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            ], [
                'proveedor_id.required' => 'El proveedor es obligatorio.',
                'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
                'tipo_documento_comprobante_id.required' => 'El tipo de documento del comprobante es obligatorio.',
                'tipo_documento_comprobante_id.exists' => 'El tipo de documento seleccionado no existe.',
                'numero_comprobante.required' => 'El número de comprobante es obligatorio.',
                'numero_comprobante.max' => 'El número de comprobante no puede superar los 50 caracteres.',
                'fecha_compra.required' => 'La fecha de compra es obligatoria.',
                'fecha_compra.date' => 'La fecha de compra no es válida.',
                'estado.required' => 'El estado es obligatorio.',
                'detalles.required' => 'Los detalles de la compra son obligatorios.',
                'detalles.array' => 'Los detalles deben enviarse como un arreglo.',
                'detalles.min' => 'La compra debe tener al menos un detalle.',
                'detalles.*.producto_id.required' => 'El producto es obligatorio.',
                'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
                'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
                'detalles.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
                'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
                'detalles.*.costo_unitario.required' => 'El costo unitario es obligatorio.',
                'detalles.*.costo_unitario.numeric' => 'El costo unitario debe ser numérico.',
                'detalles.*.costo_unitario.min' => 'El costo unitario no puede ser negativo.',
                'archivo_pdf.file' => 'El comprobante debe ser un archivo válido.',
                'archivo_pdf.mimes' => 'El comprobante debe ser un archivo PDF.',
                'archivo_pdf.max' => 'El archivo PDF no debe superar los 5MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }
        
        $existeCompra = Compra::where('proveedor_id', $request->proveedor_id)
            ->where('numero_comprobante', $request->numero_comprobante)
            ->exists();

        if ($existeCompra) {
            return response()->json([
                'codigo' => 409,
                'mensaje' => 'Ya existe una compra registrada con este número de comprobante para el proveedor seleccionado.',
            ], 409);
        }

        DB::beginTransaction();

        try {
            $usuarioId = Auth::id();

            $rutaPdf = null;
            if ($request->hasFile('archivo_pdf')) {
                $rutaPdf = $request->file('archivo_pdf')->store('compras/comprobantes', 'public');
            }

            $costoTotal = 0;
            foreach ($request->detalles as $item) {
                $costoTotal += $item['cantidad'] * $item['costo_unitario'];
            }

            $compra = new Compra();
            $compra->proveedor_id = $request->proveedor_id;
            $compra->usuario_id = $usuarioId;
            $compra->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $compra->numero_comprobante = $request->numero_comprobante;
            $compra->costo_total = $costoTotal;
            $compra->fecha_compra = $request->fecha_compra;
            $compra->ruta_pdf = $rutaPdf;
            $compra->estado = $request->estado;
            $compra->save();

            $tipoMovCompra = TipoMovimientoInventario::where('siglas', 'COMPRA')->value('id');

            if (!$tipoMovCompra) {
                throw new \RuntimeException('No existe el tipo de movimiento COMPRA en la tabla tipo_movimiento_inventarios.');
            }

            foreach ($request->detalles as $item) {
                $subtotal = $item['cantidad'] * $item['costo_unitario'];

                $detalle = new DetalleCompra();
                $detalle->compra_id = $compra->id;
                $detalle->producto_id = $item['producto_id'];
                $detalle->cantidad = $item['cantidad'];
                $detalle->costo_unitario = $item['costo_unitario'];
                $detalle->subtotal = $subtotal;
                $detalle->estado = 1;
                $detalle->save();

                $producto = Producto::find($item['producto_id']);

                if (!$producto) {
                    throw new \RuntimeException('El producto seleccionado no existe.');
                }

                $producto->increment('stock_actual', $item['cantidad']);

                $movimiento = new Inventario();
                $movimiento->producto_id = $producto->id;
                $movimiento->tipo_movimiento_inventario_id = $tipoMovCompra;
                $movimiento->cantidad = $item['cantidad'];
                $movimiento->tipo_referencia = 'Compra';
                $movimiento->referencia_id = $compra->id;
                $movimiento->motivo = "Ingreso por compra (Comprobante: {$compra->numero_comprobante})";
                $movimiento->usuario_id = $usuarioId;
                $movimiento->estado = 1;
                $movimiento->save();
            }

            $tipoTransaccionCompra = TipoTransaccion::where('siglas', 'COM')->value('id');

            if (!$tipoTransaccionCompra) {
                throw new \RuntimeException('No existe el tipo de transacción COM en la tabla tipo_transacciones.');
            }

            $transaccion = new Transaccion();
            $transaccion->tipo_transaccion_id = $tipoTransaccionCompra;
            $transaccion->tipo_referencia = 'Compra';
            $transaccion->referencia_id = $compra->id;
            $transaccion->monto = $compra->costo_total;
            $transaccion->motivo = "Salida de dinero por compra (Comprobante: {$compra->numero_comprobante})";
            $transaccion->usuario_id = $usuarioId;
            $transaccion->estado = 1;
            $transaccion->save();

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Compra registrada correctamente',
                'compra' => CompraResource::make($compra->load([
                    'proveedor.persona',
                    'usuario.persona',
                    'tipoDocumentoComprobante',
                    'detalles.producto',
                ])),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error crítico al registrar la compra.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Put(
        path: '/api/compras/{compra}',
        summary: 'Actualizar compra',
        description: 'Actualiza una compra existente junto con sus detalles. Recalcula automáticamente el costo total, ajusta el stock de los productos, registra los movimientos correspondientes en el Kardex y actualiza la transacción de caja asociada. Permite reemplazar el comprobante PDF.',
        tags: ['Compras'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'compra',
                description: 'ID de la compra a actualizar.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: [
                        'proveedor_id',
                        'tipo_documento_comprobante_id',
                        'numero_comprobante',
                        'fecha_compra',
                        'estado',
                        'detalles'
                    ],
                    properties: [
                        new OA\Property(
                            property: '_method',
                            type: 'string',
                            description: 'Laravel Method Spoofing para procesar la petición como PUT cuando se utiliza multipart/form-data.',
                            example: 'PUT'
                        ),
                        new OA\Property(
                            property: 'proveedor_id',
                            type: 'integer',
                            description: 'ID del proveedor de la compra.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'tipo_documento_comprobante_id',
                            type: 'integer',
                            description: 'ID del tipo de documento del comprobante.',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'numero_comprobante',
                            type: 'string',
                            maxLength: 50,
                            description: 'Número del comprobante de compra.',
                            example: 'F001-00001234'
                        ),
                        new OA\Property(
                            property: 'fecha_compra',
                            type: 'string',
                            format: 'date',
                            description: 'Fecha de la compra.',
                            example: '2026-08-11'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            description: 'Estado de la compra: 1 activa, 0 inactiva.',
                            enum: [0, 1],
                            example: 1
                        ),
                        new OA\Property(
                            property: 'archivo_pdf',
                            type: 'string',
                            format: 'binary',
                            description: 'Nuevo comprobante PDF. Si se envía, reemplaza el archivo anterior. Máximo 5MB.'
                        ),
                        new OA\Property(
                            property: 'detalles',
                            type: 'array',
                            minItems: 1,
                            description: 'Detalles de la compra. Los detalles con ID existente se actualizan; los que no tengan ID se crean. Los detalles existentes que no sean enviados se eliminan.',
                            items: new OA\Items(
                                type: 'object',
                                required: [
                                    'producto_id',
                                    'cantidad',
                                    'costo_unitario'
                                ],
                                properties: [
                                    new OA\Property(
                                        property: 'id',
                                        type: 'integer',
                                        nullable: true,
                                        description: 'ID del detalle existente. Omitir este campo para crear un nuevo detalle.',
                                        example: 1
                                    ),
                                    new OA\Property(
                                        property: 'producto_id',
                                        type: 'integer',
                                        description: 'ID del producto.',
                                        example: 10
                                    ),
                                    new OA\Property(
                                        property: 'cantidad',
                                        type: 'integer',
                                        minimum: 1,
                                        description: 'Cantidad comprada.',
                                        example: 20
                                    ),
                                    new OA\Property(
                                        property: 'costo_unitario',
                                        type: 'number',
                                        format: 'float',
                                        minimum: 0,
                                        description: 'Costo unitario de compra.',
                                        example: 150.50
                                    )
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Compra actualizada correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),
            new OA\Response(
                response: 404,
                description: 'Compra no encontrada'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error crítico al actualizar la compra'
            )
        ]
    )]

    public function update(Request $request, Compra $compra)
    {
        try {
            $request->validate([
                'proveedor_id' => ['required', 'integer', 'exists:proveedores,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'numero_comprobante' => ['required', 'string', 'max:50'],
                'fecha_compra' => ['required', 'date'],
                'estado' => ['required', 'boolean'],
                'archivo_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                'detalles' => ['required', 'array', 'min:1'],
                'detalles.*.id' => ['nullable', 'integer', 'exists:detalle_compras,id'],
                'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad' => ['required', 'integer', 'min:1'],
                'detalles.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            ], [
                'proveedor_id.required' => 'El proveedor es obligatorio.',
                'proveedor_id.exists' => 'El proveedor seleccionado no existe.',
                'tipo_documento_comprobante_id.required' => 'El tipo de documento del comprobante es obligatorio.',
                'tipo_documento_comprobante_id.exists' => 'El tipo de documento seleccionado no existe.',
                'numero_comprobante.required' => 'El número de comprobante es obligatorio.',
                'numero_comprobante.max' => 'El número de comprobante no puede superar los 50 caracteres.',
                'fecha_compra.required' => 'La fecha de compra es obligatoria.',
                'fecha_compra.date' => 'La fecha de compra no es válida.',
                'estado.required' => 'El estado es obligatorio.',
                'detalles.required' => 'Los detalles de la compra son obligatorios.',
                'detalles.array' => 'Los detalles deben enviarse como un arreglo.',
                'detalles.min' => 'La compra debe tener al menos un detalle.',
                'detalles.*.id.exists' => 'El detalle de compra seleccionado no existe.',
                'detalles.*.producto_id.required' => 'El producto es obligatorio.',
                'detalles.*.producto_id.exists' => 'El producto seleccionado no existe.',
                'detalles.*.cantidad.required' => 'La cantidad es obligatoria.',
                'detalles.*.cantidad.integer' => 'La cantidad debe ser un número entero.',
                'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a 0.',
                'detalles.*.costo_unitario.required' => 'El costo unitario es obligatorio.',
                'detalles.*.costo_unitario.numeric' => 'El costo unitario debe ser numérico.',
                'detalles.*.costo_unitario.min' => 'El costo unitario no puede ser negativo.',
                'archivo_pdf.file' => 'El comprobante debe ser un archivo válido.',
                'archivo_pdf.mimes' => 'El comprobante debe ser un archivo PDF.',
                'archivo_pdf.max' => 'El archivo PDF no debe superar los 5MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        $existeCompra = Compra::where('proveedor_id', $request->proveedor_id)
            ->where('numero_comprobante', $request->numero_comprobante)
            ->where('id', '!=', $compra->id)
            ->exists();

        if ($existeCompra) {
            return response()->json([
                'codigo' => 409,
                'mensaje' => 'Ya existe otra compra registrada con este número de comprobante para el proveedor seleccionado.',
            ], 409);
        }

        DB::beginTransaction();

        try {
            $usuarioId = Auth::id();

            if ($request->hasFile('archivo_pdf')) {
                if ($compra->ruta_pdf && \Illuminate\Support\Facades\Storage::disk('public')->exists($compra->ruta_pdf)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($compra->ruta_pdf);
                }

                $compra->ruta_pdf = $request->file('archivo_pdf')->store('compras/comprobantes', 'public');
            }

            $costoTotal = 0;

            foreach ($request->detalles as $item) {
                $costoTotal += $item['cantidad'] * $item['costo_unitario'];
            }

            $compra->proveedor_id = $request->proveedor_id;
            $compra->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $compra->numero_comprobante = $request->numero_comprobante;
            $compra->costo_total = $costoTotal;
            $compra->fecha_compra = $request->fecha_compra;
            $compra->estado = $request->estado;
            $compra->save();

            $tipoMovCompra = TipoMovimientoInventario::where('siglas', 'COMPRA')->value('id');
            $tipoMovAjuste = TipoMovimientoInventario::where('siglas', 'AJUSTE')->value('id');

            if (!$tipoMovCompra) {
                throw new \RuntimeException('No existe el tipo de movimiento COMPRA en la tabla tipo_movimiento_inventarios.');
            }

            if (!$tipoMovAjuste) {
                throw new \RuntimeException('No existe el tipo de movimiento AJUSTE en la tabla tipo_movimiento_inventarios.');
            }

            $detallesActuales = DetalleCompra::where('compra_id', $compra->id)->get();
            $idsEntrantes = collect($request->detalles)->pluck('id')->filter()->map(fn($id) => (int) $id)->toArray();

            foreach ($detallesActuales as $detalleViejo) {
                if (!in_array($detalleViejo->id, $idsEntrantes)) {
                    $producto = Producto::find($detalleViejo->producto_id);

                    if (!$producto) {
                        throw new \RuntimeException("El producto {$detalleViejo->producto_id} ya no existe.");
                    }

                    if ($producto->stock_actual < $detalleViejo->cantidad) {
                        throw new \RuntimeException("No se puede eliminar el detalle del producto {$producto->nombre} porque el stock actual es insuficiente para revertir la compra.");
                    }

                    $producto->decrement('stock_actual', $detalleViejo->cantidad);

                    $movimiento = new Inventario();
                    $movimiento->producto_id = $producto->id;
                    $movimiento->tipo_movimiento_inventario_id = $tipoMovAjuste;
                    $movimiento->cantidad = $detalleViejo->cantidad;
                    $movimiento->tipo_referencia = 'Compra';
                    $movimiento->referencia_id = $compra->id;
                    $movimiento->motivo = "Ajuste negativo por eliminación del producto en la compra {$compra->numero_comprobante}";
                    $movimiento->usuario_id = $usuarioId;
                    $movimiento->estado = 1;
                    $movimiento->save();

                    $detalleViejo->delete();
                }
            }

            foreach ($request->detalles as $item) {
                $subtotal = $item['cantidad'] * $item['costo_unitario'];

                if (!empty($item['id'])) {
                    $detalle = DetalleCompra::where('id', $item['id'])
                        ->where('compra_id', $compra->id)
                        ->first();

                    if (!$detalle) {
                        throw new \RuntimeException("El detalle {$item['id']} no pertenece a la compra {$compra->id}.");
                    }

                    $productoAnterior = Producto::find($detalle->producto_id);

                    if (!$productoAnterior) {
                        throw new \RuntimeException("El producto {$detalle->producto_id} ya no existe.");
                    }

                    if ($detalle->producto_id != $item['producto_id']) {
                        if ($productoAnterior->stock_actual < $detalle->cantidad) {
                            throw new \RuntimeException("No se puede cambiar el producto del detalle {$detalle->id} porque el stock actual del producto anterior es insuficiente para revertir la compra.");
                        }

                        $productoAnterior->decrement('stock_actual', $detalle->cantidad);

                        $movAjuste = new Inventario();
                        $movAjuste->producto_id = $productoAnterior->id;
                        $movAjuste->tipo_movimiento_inventario_id = $tipoMovAjuste;
                        $movAjuste->cantidad = $detalle->cantidad;
                        $movAjuste->tipo_referencia = 'Compra';
                        $movAjuste->referencia_id = $compra->id;
                        $movAjuste->motivo = "Ajuste negativo por cambio de producto en la compra {$compra->numero_comprobante}";
                        $movAjuste->usuario_id = $usuarioId;
                        $movAjuste->estado = 1;
                        $movAjuste->save();

                        $productoNuevo = Producto::find($item['producto_id']);

                        if (!$productoNuevo) {
                            throw new \RuntimeException("El producto {$item['producto_id']} no existe.");
                        }

                        $productoNuevo->increment('stock_actual', $item['cantidad']);

                        $movCompra = new Inventario();
                        $movCompra->producto_id = $productoNuevo->id;
                        $movCompra->tipo_movimiento_inventario_id = $tipoMovCompra;
                        $movCompra->cantidad = $item['cantidad'];
                        $movCompra->tipo_referencia = 'Compra';
                        $movCompra->referencia_id = $compra->id;
                        $movCompra->motivo = "Ingreso por cambio de producto en la compra {$compra->numero_comprobante}";
                        $movCompra->usuario_id = $usuarioId;
                        $movCompra->estado = 1;
                        $movCompra->save();

                    } elseif ($detalle->cantidad != $item['cantidad']) {
                        $diferencia = $item['cantidad'] - $detalle->cantidad;

                        if ($diferencia > 0) {
                            $productoAnterior->increment('stock_actual', $diferencia);

                            $movimiento = new Inventario();
                            $movimiento->producto_id = $productoAnterior->id;
                            $movimiento->tipo_movimiento_inventario_id = $tipoMovCompra;
                            $movimiento->cantidad = $diferencia;
                            $movimiento->tipo_referencia = 'Compra';
                            $movimiento->referencia_id = $compra->id;
                            $movimiento->motivo = "Ajuste positivo por aumento de cantidad en la compra {$compra->numero_comprobante}";
                            $movimiento->usuario_id = $usuarioId;
                            $movimiento->estado = 1;
                            $movimiento->save();

                        } else {
                            $cantidadReducida = abs($diferencia);

                            if ($productoAnterior->stock_actual < $cantidadReducida) {
                                throw new \RuntimeException("No se puede reducir la cantidad del producto {$productoAnterior->nombre} porque el stock actual es insuficiente.");
                            }

                            $productoAnterior->decrement('stock_actual', $cantidadReducida);

                            $movimiento = new Inventario();
                            $movimiento->producto_id = $productoAnterior->id;
                            $movimiento->tipo_movimiento_inventario_id = $tipoMovAjuste;
                            $movimiento->cantidad = $cantidadReducida;
                            $movimiento->tipo_referencia = 'Compra';
                            $movimiento->referencia_id = $compra->id;
                            $movimiento->motivo = "Ajuste negativo por reducción de cantidad en la compra {$compra->numero_comprobante}";
                            $movimiento->usuario_id = $usuarioId;
                            $movimiento->estado = 1;
                            $movimiento->save();
                        }
                    }

                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $item['cantidad'];
                    $detalle->costo_unitario = $item['costo_unitario'];
                    $detalle->subtotal = $subtotal;
                    $detalle->estado = 1;
                    $detalle->save();

                } else {
                    $detalle = new DetalleCompra();
                    $detalle->compra_id = $compra->id;
                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $item['cantidad'];
                    $detalle->costo_unitario = $item['costo_unitario'];
                    $detalle->subtotal = $subtotal;
                    $detalle->estado = 1;
                    $detalle->save();

                    $producto = Producto::find($item['producto_id']);

                    if (!$producto) {
                        throw new \RuntimeException("El producto {$item['producto_id']} no existe.");
                    }

                    $producto->increment('stock_actual', $item['cantidad']);

                    $movimiento = new Inventario();
                    $movimiento->producto_id = $producto->id;
                    $movimiento->tipo_movimiento_inventario_id = $tipoMovCompra;
                    $movimiento->cantidad = $item['cantidad'];
                    $movimiento->tipo_referencia = 'Compra';
                    $movimiento->referencia_id = $compra->id;
                    $movimiento->motivo = "Ingreso por nuevo producto agregado a la compra {$compra->numero_comprobante}";
                    $movimiento->usuario_id = $usuarioId;
                    $movimiento->estado = 1;
                    $movimiento->save();
                }
            }

            $tipoTransaccionCompra = TipoTransaccion::where('siglas', 'COM')->value('id');

            if (!$tipoTransaccionCompra) {
                throw new \RuntimeException('No existe el tipo de transacción COM en la tabla tipo_transacciones.');
            }

            $transaccion = Transaccion::where('tipo_referencia', 'Compra')
                ->where('referencia_id', $compra->id)
                ->first();

            if ($transaccion) {
                $transaccion->tipo_transaccion_id = $tipoTransaccionCompra;
                $transaccion->monto = $compra->costo_total;
                $transaccion->motivo = "Salida de dinero por compra editada (Comprobante: {$compra->numero_comprobante})";
                $transaccion->usuario_id = $usuarioId;
                $transaccion->estado = 1;
                $transaccion->save();
            } else {
                $transaccion = new Transaccion();
                $transaccion->tipo_transaccion_id = $tipoTransaccionCompra;
                $transaccion->tipo_referencia = 'Compra';
                $transaccion->referencia_id = $compra->id;
                $transaccion->monto = $compra->costo_total;
                $transaccion->motivo = "Salida de dinero por compra editada (Comprobante: {$compra->numero_comprobante})";
                $transaccion->usuario_id = $usuarioId;
                $transaccion->estado = 1;
                $transaccion->save();
            }

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Compra actualizada correctamente',
                'compra' => CompraResource::make($compra->load([
                    'proveedor.persona',
                    'usuario.persona',
                    'tipoDocumentoComprobante',
                    'detalles.producto',
                ])),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'mensaje' => 'Error crítico al actualizar la compra.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}