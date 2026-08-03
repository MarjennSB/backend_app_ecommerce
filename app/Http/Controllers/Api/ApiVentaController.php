<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Venta\VentaCollection;
use App\Http\Resources\Venta\VentaResource;
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
        $this->middleware('can:eliminar_venta')->only('destroy');
    }

    #[OA\Get(
        path: '/api/ventas',
        summary: 'Listar ventas',
        description: 'Obtiene una lista paginada de todas las ventas. Permite buscar por número de comprobante o por el nombre/apellido/documento del cliente. Carga automáticamente los detalles, cliente y método de pago.',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda (coincidencia parcial en número de comprobante o nombres del cliente)',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'F002'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página (por defecto: 10)',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de ventas obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]
    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $ventas = Venta::with(['cliente.persona', 'usuario', 'tipoDocumentoComprobante', 'tipoMetodoPago', 'detalles.producto'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    
                    // 1. Buscar por el número de comprobante
                    $q->where('numero_comprobante', 'like', "%{$search}%")
                      
                      // 2. Buscar dentro de la relación cliente -> persona
                      ->orWhereHas('cliente.persona', function ($qPersona) use ($search) {
                          $qPersona->where('nombres', 'like', "%{$search}%")
                                   ->orWhere('apellido_paterno', 'like', "%{$search}%")
                                   ->orWhere('numero_documento', 'like', "%{$search}%");
                      });
                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'ventas' => new VentaCollection($ventas->getCollection()),
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

    #[OA\Post(
        path: '/api/ventas',
        summary: 'Registrar nueva venta',
        description: 'Crea una venta validando previamente que exista stock suficiente. El backend calcula automáticamente los subtotales y el precio total de la venta.',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
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
                    ],
                    properties: [
                        new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_documento_comprobante_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_metodo_pago_id', type: 'integer', example: 1, description: 'ID del método de pago (Efectivo, Tarjeta, etc.)'),
                        new OA\Property(property: 'numero_comprobante', type: 'string', maxLength: 20, example: 'B001-000456'),
                        new OA\Property(property: 'fecha_venta', type: 'string', format: 'date', example: '2026-07-30'),
                        new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1),
                        new OA\Property(
                            property: 'archivo_pdf', 
                            type: 'string', 
                            format: 'binary', 
                            description: 'Comprobante escaneado (Opcional)'
                        ),
                        new OA\Property(property: 'detalles[0][producto_id]', type: 'integer', example: 1),
                        new OA\Property(property: 'detalles[0][cantidad]', type: 'integer', example: 2),
                        new OA\Property(property: 'detalles[0][precio_unitario]', type: 'number', format: 'float', example: 150.00),
                        // Nota: ¡Ya no pedimos subtotal ni precio_total!
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Venta registrada correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación o stock insuficiente'),
            new OA\Response(response: 500, description: 'Error interno')
        ]
    )]
    public function store(Request $request)
    {
        try {
            // 1. Validación estricta
            $request->validate([
                'cliente_id'                    => ['required', 'integer', 'exists:clientes,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'tipo_metodo_pago_id'           => ['required', 'integer', 'exists:tipo_metodo_pagos,id'],
                'numero_comprobante'            => ['required', 'string', 'max:20'],
                'fecha_venta'                   => ['required', 'date'],
                'estado'                        => ['required', 'boolean'],
                'archivo_pdf'                   => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                
                'detalles'                      => ['required', 'array', 'min:1'],
                'detalles.*.producto_id'        => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad'           => ['required', 'integer', 'min:1'],
                'detalles.*.precio_unitario'    => ['required', 'numeric', 'min:0'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['mensaje' => 'Errores de validación', 'errors' => $e->errors()], 422);
        }

        // 2. REVISIÓN DE STOCK
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
                'mensaje' => 'No hay stock suficiente para uno o más productos',
                'errors'  => $erroresStock
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Guardar PDF si existe
            $rutaPdf = null;
            if ($request->hasFile('archivo_pdf')) {
                $rutaPdf = $request->file('archivo_pdf')->store('ventas/comprobantes', 'public');
            }

            // Calcular Precio Total
            $precioTotal = 0;
            foreach ($request->detalles as $item) {
                $precioTotal += ($item['cantidad'] * $item['precio_unitario']);
            }

            // Crear Venta (Cabecera)
            $venta = new Venta();
            $venta->cliente_id = $request->cliente_id;
            $venta->usuario_id = Auth::id();
            $venta->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $venta->tipo_metodo_pago_id = $request->tipo_metodo_pago_id;
            $venta->numero_comprobante = $request->numero_comprobante;
            $venta->precio_total = $precioTotal; // Asignamos el valor calculado
            $venta->fecha_venta = $request->fecha_venta;
            $venta->ruta_pdf = $rutaPdf;
            $venta->estado = $request->estado;
            $venta->save();

            // Preparar Kardex
            $tipoMovVenta = TipoMovimientoInventario::where('siglas', 'VENTA')->value('id');
            $tipoTransaccionVenta = TipoTransaccion::where('siglas', 'VEN')->value('id');

            // Crear Detalles y Movimientos
            foreach ($request->detalles as $item) {
                
                $subtotal = $item['cantidad'] * $item['precio_unitario'];

                // A) Registrar Detalle de Venta
                $detalle = new DetalleVenta();
                $detalle->venta_id = $venta->id;
                $detalle->producto_id = $item['producto_id'];
                $detalle->cantidad = $item['cantidad'];
                $detalle->precio_unitario = $item['precio_unitario'];
                $detalle->subtotal = $subtotal;
                $detalle->estado = true;
                $detalle->save();

                // B) RESTAR STOCK
                Producto::find($item['producto_id'])->decrement('cantidad', $item['cantidad']);

                // C) REGISTRO EN EL KARDEX (INVENTARIO)
                $movimiento = new Inventario();
                $movimiento->producto_id = $item['producto_id'];
                $movimiento->tipo_movimiento_inventario_id = $tipoMovVenta;
                $movimiento->cantidad = $item['cantidad'];
                $movimiento->tipo_referencia = 'Venta';
                $movimiento->referencia_id = $venta->id;
                $movimiento->motivo = "Salida por venta (Comprobante: {$venta->numero_comprobante})";
                $movimiento->usuario_id = Auth::id();
                $movimiento->estado = true;
                $movimiento->save();

                // D) REGISTRO DE TRANSACCIÓN (MÓDULO FINANCIERO)
                if (empty($tipoTransaccionVenta)) {
                    throw new \RuntimeException('No existe el tipo de transacción VEN en la tabla tipo_transacciones.');
                }

                $transaccion = new Transaccion();
                $transaccion->tipo_transaccion_id = $tipoTransaccionVenta;
                $transaccion->tipo_referencia = 'Venta';
                $transaccion->referencia_id = $venta->id;
                $transaccion->monto = $venta->precio_total; // Usamos el total que calculamos antes
                $transaccion->motivo = "Ingreso por Venta (Comprobante: {$venta->numero_comprobante})";
                $transaccion->usuario_id = Auth::id();
                $transaccion->estado = 1;
                $transaccion->save();
            }

            DB::commit();

            return response()->json([
                'codigo'  => 200,
                'mensaje' => 'Venta registrada correctamente',
                'venta'   => VentaResource::make(
                    $venta->load(['cliente.persona', 'usuario', 'tipoDocumentoComprobante', 'tipoMetodoPago', 'detalles.producto'])
                ),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'mensaje' => 'Error crítico al registrar la venta.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/ventas/{id}',
        summary: 'Actualizar venta y detalles',
        description: 'Actualiza la venta recalculando los totales y ajustando el stock de inventario. Valida el stock antes de realizar cualquier cambio. NOTA: Usa POST con _method=PUT.',
        tags: ['Ventas'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la venta a actualizar',
                schema: new OA\Schema(type: 'integer', example: 1)
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
                    ],
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT'),
                        new OA\Property(property: 'cliente_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_documento_comprobante_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_metodo_pago_id', type: 'integer', example: 1),
                        new OA\Property(property: 'numero_comprobante', type: 'string', maxLength: 20, example: 'B001-000456'),
                        new OA\Property(property: 'fecha_venta', type: 'string', format: 'date', example: '2026-07-30'),
                        new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1),
                        new OA\Property(property: 'archivo_pdf', type: 'string', format: 'binary', description: 'Reemplazar PDF (Opcional)'),
                        
                        // Detalles
                        new OA\Property(property: 'detalles[0][id]', type: 'integer', example: 1, description: 'ID del detalle (omitir si es nuevo)'),
                        new OA\Property(property: 'detalles[0][producto_id]', type: 'integer', example: 1),
                        new OA\Property(property: 'detalles[0][cantidad]', type: 'integer', example: 3),
                        new OA\Property(property: 'detalles[0][precio_unitario]', type: 'number', format: 'float', example: 150.00),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Venta actualizada correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación o stock'),
            new OA\Response(response: 404, description: 'Venta no encontrada'),
            new OA\Response(response: 500, description: 'Error interno')
        ]
    )]
    public function update(Request $request, Venta $venta)
    {
        try {
            $request->validate([
                'cliente_id'                    => ['required', 'integer', 'exists:clientes,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'tipo_metodo_pago_id'           => ['required', 'integer', 'exists:tipo_metodo_pagos,id'],
                'numero_comprobante'            => ['required', 'string', 'max:20'],
                'fecha_venta'                   => ['required', 'date'],
                'estado'                        => ['required', 'boolean'],
                'archivo_pdf'                   => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                
                'detalles'                      => ['required', 'array', 'min:1'],
                'detalles.*.id'                 => ['nullable', 'integer', 'exists:detalle_ventas,id'],
                'detalles.*.producto_id'        => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad'           => ['required', 'integer', 'min:1'],
                'detalles.*.precio_unitario'    => ['required', 'numeric', 'min:0'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['mensaje' => 'Errores de validación', 'errors' => $e->errors()], 422);
        }

        // 1. VALIDACIÓN MAESTRA DE STOCK PARA EDICIÓN
        $erroresStock = [];
        foreach ($request->detalles as $index => $item) {
            $producto = Producto::find($item['producto_id']);
            $stockDisponible = $producto->cantidad;

            // Si el detalle ya existía, sumamos momentáneamente la cantidad
            // para saber el stock "real" disponible para esta edición.
            if (isset($item['id']) && $item['id']) {
                $detalleViejo = DetalleVenta::find($item['id']);
                if ($detalleViejo && $detalleViejo->producto_id == $item['producto_id']) {
                    $stockDisponible += $detalleViejo->cantidad;
                }
            }

            if ($stockDisponible < $item['cantidad']) {
                $erroresStock["detalles.{$index}.cantidad"] = [
                    "Stock insuficiente para '{$producto->nombre}'. Disponible para esta edición: {$stockDisponible}."
                ];
            }
        }

        if (!empty($erroresStock)) {
            return response()->json(['mensaje' => 'No hay stock suficiente', 'errors' => $erroresStock], 422);
        }

        DB::beginTransaction();

        try {
            // 2. Actualizar PDF (eliminando el viejo si es necesario)
            if ($request->hasFile('archivo_pdf')) {
                if ($venta->ruta_pdf && \Illuminate\Support\Facades\Storage::disk('public')->exists($venta->ruta_pdf)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($venta->ruta_pdf);
                }
                $venta->ruta_pdf = $request->file('archivo_pdf')->store('ventas/comprobantes', 'public');
            }

            // 3. Recalcular el total automático
            $precioTotal = 0;
            foreach ($request->detalles as $item) {
                $precioTotal += ($item['cantidad'] * $item['precio_unitario']);
            }

            // 4. Actualizar cabecera
            $venta->cliente_id = $request->cliente_id;
            $venta->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $venta->tipo_metodo_pago_id = $request->tipo_metodo_pago_id;
            $venta->numero_comprobante = $request->numero_comprobante;
            $venta->fecha_venta = $request->fecha_venta;
            $venta->precio_total = $precioTotal;
            $venta->estado = $request->estado;
            $venta->save();

            // 5. Gestión avanzada de Detalles y Stock
            $tipoMovVenta = TipoMovimientoInventario::where('siglas', 'VENTA')->value('id');
            $tipoMovAjuste = TipoMovimientoInventario::where('siglas', 'AJUSTE')->value('id');

            $detallesEntrantes = collect($request->detalles);
            $idsEntrantes = $detallesEntrantes->pluck('id')->filter()->toArray();
            
            // 5.1 Eliminar detalles quitados por el usuario
            $detallesAEliminar = DetalleVenta::where('venta_id', $venta->id)
                                             ->whereNotIn('id', $idsEntrantes)
                                             ->get();

            foreach ($detallesAEliminar as $detalleViejo) {
                // 1. Devolvemos el producto al inventario
                Producto::find($detalleViejo->producto_id)->increment('cantidad', $detalleViejo->cantidad);
                
                // 2. Registro en Kardex (Ajuste positivo por eliminación) - Forma segura
                $movimiento = new Inventario();
                $movimiento->producto_id = $detalleViejo->producto_id;
                $movimiento->tipo_movimiento_inventario_id = $tipoMovAjuste;
                $movimiento->cantidad = $detalleViejo->cantidad;
                $movimiento->tipo_referencia = 'Venta';
                $movimiento->referencia_id = $venta->id;
                $movimiento->motivo = "Ajuste positivo: Producto devuelto tras eliminación en edición de la venta";
                $movimiento->usuario_id = Auth::id();
                $movimiento->estado = true;
                $movimiento->save();

                // 3. Borramos el detalle
                $detalleViejo->delete();
            }

            // 5.2 Actualizar y Crear nuevos detalles
            foreach ($request->detalles as $item) {
                $subtotalCalculado = $item['cantidad'] * $item['precio_unitario'];

                if (isset($item['id']) && $item['id']) {
                    $detalle = DetalleVenta::find($item['id']);
                    
                    // Solo si cambiaron el producto o la cantidad, registramos la auditoría
                    if ($detalle->producto_id != $item['producto_id'] || $detalle->cantidad != $item['cantidad']) {
                        
                        // PASO 1: Revertir lo viejo (DEVOLVER stock y registrar Kardex)
                        Producto::find($detalle->producto_id)->increment('cantidad', $detalle->cantidad);
                        
                        $movAjuste = new Inventario();
                        $movAjuste->producto_id = $detalle->producto_id;
                        $movAjuste->tipo_movimiento_inventarios_id = $tipoMovAjuste;
                        $movAjuste->cantidad = $detalle->cantidad;
                        $movAjuste->tipo_referencia = 'Venta';
                        $movAjuste->referencia_id = $venta->id;
                        $movAjuste->motivo = "Ajuste positivo por reversión previa a edición de venta";
                        $movAjuste->usuario_id = Auth::id();
                        $movAjuste->estado = true;
                        $movAjuste->save();

                        // PASO 2: Extraer lo nuevo (RESTAR stock y registrar Kardex)
                        Producto::find($item['producto_id'])->decrement('cantidad', $item['cantidad']);
                        
                        $movVenta = new Inventario();
                        $movVenta->producto_id = $item['producto_id'];
                        $movVenta->tipo_movimiento_inventarios_id = $tipoMovVenta;
                        $movVenta->cantidad = $item['cantidad'];
                        $movVenta->tipo_referencia = 'Venta';
                        $movVenta->referencia_id = $venta->id;
                        $movVenta->motivo = "Salida corregida por edición de venta";
                        $movVenta->usuario_id = Auth::id();
                        $movVenta->estado = true;
                        $movVenta->save();
                    }

                    // Guardamos la actualización del detalle
                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $item['cantidad'];
                    $detalle->precio_unitario = $item['precio_unitario'];
                    $detalle->subtotal = $subtotalCalculado;
                    $detalle->save();
                    
                } else {
                    // Detalle Nuevo agregado a la venta
                    $detalle = new DetalleVenta();
                    $detalle->venta_id = $venta->id;
                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $item['cantidad'];
                    $detalle->precio_unitario = $item['precio_unitario'];
                    $detalle->subtotal = $subtotalCalculado;
                    $detalle->estado = true;
                    $detalle->save();

                    // RESTAMOS stock por este nuevo producto
                    Producto::find($item['producto_id'])->decrement('cantidad', $item['cantidad']);

                    // Registro Kardex
                    $movNuevo = new Inventario();
                    $movNuevo->producto_id = $item['producto_id'];
                    $movNuevo->tipo_movimiento_inventarios_id = $tipoMovVenta;
                    $movNuevo->cantidad = $item['cantidad'];
                    $movNuevo->tipo_referencia = 'Venta';
                    $movNuevo->referencia_id = $venta->id;
                    $movNuevo->motivo = "Salida por producto agregado durante la edición de la venta";
                    $movNuevo->usuario_id = Auth::id();
                    $movNuevo->estado = true;
                    $movNuevo->save();

                    // Actualizamos la transacción financiera asociada a la venta editada
                    $transaccion = Transaccion::where('tipo_referencia', 'Venta')
                                                        ->where('referencia_id', $venta->id)
                                                        ->first();
                    if ($transaccion) {
                        $transaccion->monto = $venta->precio_total;
                        $transaccion->motivo = "Ingreso por Venta editada (Comprobante: {$venta->numero_comprobante})";
                        $transaccion->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'codigo'  => 200,
                'mensaje' => 'Venta actualizada correctamente',
                'venta'   => VentaResource::make(
                    $venta->load(['cliente.persona', 'usuario', 'tipoDocumentoComprobante', 'tipoMetodoPago', 'detalles.producto'])
                ),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'mensaje' => 'Error crítico al actualizar la venta.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}