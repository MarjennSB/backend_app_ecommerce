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
use Illuminate\Support\Facades\Storage;

class ApiCompraController extends Controller
{

    public function __construct()
        {
            $this->middleware('jwt.auth');
            $this->middleware('can:listar_compra')->only('index');
            $this->middleware('can:registrar_compra')->only('store');
            $this->middleware('can:editar_compra')->only('update');
            $this->middleware('can:eliminar_compra')->only('destroy');
        }

    #[OA\Get(
        path: '/api/compras',
        summary: 'Listar compras',
        description: 'Obtiene una lista paginada de todas las compras. Permite buscar por número de comprobante o por el nombre/apellido del proveedor. Carga automáticamente los detalles de la compra y los datos del producto.',
        tags: ['Compras'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda (coincidencia parcial en número de comprobante o nombres del proveedor)',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'F001'
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
                description: 'Lista de compras obtenida correctamente'
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

        // Cargamos todas las relaciones anidadas necesarias para que tu Resource funcione perfecto
        $compras = Compra::with(['proveedor.persona', 'usuario', 'tipoDocumentoComprobante', 'detalles.producto'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    
                    // 1. Buscar por el número de comprobante de la compra
                    $q->where('numero_comprobante', 'ilike', "%{$search}%")
                      
                      // 2. Buscar dentro de la relación proveedor -> persona
                      ->orWhereHas('proveedor.persona', function ($qPersona) use ($search) {
                          $qPersona->where('nombres', 'ilike', "%{$search}%")
                                   ->orWhere('apellido_paterno', 'ilike', "%{$search}%")
                                   ->orWhere('numero_documento', 'ilike', "%{$search}%");
                      });
                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            // Usamos CompraResource::collection si no creaste un CompraCollection aparte
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
        summary: 'Registrar nueva compra',
        description: 'Crea una compra (cabecera), registra sus múltiples productos (detalles), actualiza el stock del inventario y permite subir opcionalmente el comprobante en PDF.',
        tags: ['Compras'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'proveedor_id',
                        'tipo_documento_comprobante_id',
                        'numero_comprobante',
                        'costo_total',
                        'fecha_compra',
                        'estado',
                        // Los detalles se validarán dinámicamente
                    ],
                    properties: [
                        new OA\Property(property: 'proveedor_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_documento_comprobante_id', type: 'integer', example: 1),
                        new OA\Property(property: 'numero_comprobante', type: 'string', maxLength: 50, example: 'F001-000456'),
                        new OA\Property(property: 'costo_total', type: 'number', format: 'float', example: 1500.50),
                        new OA\Property(property: 'fecha_compra', type: 'string', format: 'date', example: '2026-07-30'),
                        new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1, description: '0 = Inactivo, 1 = Activo'),
                        new OA\Property(
                            property: 'archivo_pdf', 
                            type: 'string', 
                            format: 'binary', 
                            description: 'Comprobante de la compra escaneado (Opcional, formato PDF)'
                        ),
                        new OA\Property(
                            property: 'detalles[0][producto_id]', 
                            type: 'integer', 
                            example: 1, 
                            description: 'ID del primer producto'
                        ),
                        new OA\Property(
                            property: 'detalles[0][cantidad]', 
                            type: 'integer', 
                            example: 10, 
                            description: 'Cantidad comprada del primer producto'
                        ),
                        new OA\Property(
                            property: 'detalles[0][costo_unitario]', 
                            type: 'number', 
                            format: 'float', 
                            example: 100.50, 
                            description: 'Precio unitario de compra'
                        ),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compra registrada correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]
    public function store(Request $request)
    {
        try {
            $request->validate([
                'proveedor_id'                  => ['required', 'integer', 'exists:proveedores,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'numero_comprobante'            => ['required', 'string', 'max:50'],
                'costo_total'                   => ['required', 'numeric', 'min:0'],
                'fecha_compra'                  => ['required', 'date'],
                'estado'                        => ['required', 'boolean'],
                'archivo_pdf'                   => ['nullable', 'file', 'mimes:pdf', 'max:5120'], // Máximo 5MB
                
                // Validación del arreglo de detalles
                'detalles'                      => ['required', 'array', 'min:1'],
                'detalles.*.producto_id'        => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad'           => ['required', 'integer', 'min:1'],
                'detalles.*.costo_unitario'     => ['required', 'numeric', 'min:0'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Guardar PDF si existe
            $rutaPdf = null;
            if ($request->hasFile('archivo_pdf')) {
                $rutaPdf = $request->file('archivo_pdf')->store('compras/comprobantes', 'public');
            }

            // 1. Calcular Costo Total
            $costoTotal = 0;
            foreach ($request->detalles as $item) {
                $costoTotal += ($item['cantidad'] * $item['costo_unitario']);
            }

            // 2. Crear Compra (Cabecera)
            $compra = new Compra();
            $compra->proveedor_id = $request->proveedor_id;
            $compra->usuario_id = Auth::id();
            $compra->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $compra->numero_comprobante = $request->numero_comprobante;
            $compra->costo_total = $costoTotal; // Asignamos el valor calculado
            $compra->fecha_compra = $request->fecha_compra;
            $compra->ruta_pdf = $rutaPdf;
            $compra->estado = $request->estado;
            $compra->save();

            // Preparar Kardex
            $tipoMovCompra = TipoMovimientoInventario::where('siglas', 'COMPRA')->value('id');

            // 3. Crear Detalles y Movimientos
            foreach ($request->detalles as $item) {
                
                $subtotal = $item['cantidad'] * $item['costo_unitario'];

                // A) Guardar Detalle de Compra
                $detalle = new DetalleCompra();
                $detalle->compra_id = $compra->id;
                $detalle->producto_id = $item['producto_id'];
                $detalle->cantidad = $item['cantidad'];
                $detalle->costo_unitario = $item['costo_unitario'];
                $detalle->subtotal = $subtotal;
                $detalle->estado = true;
                $detalle->save();

                // B) SUMAR STOCK (Porque es una compra)
                Producto::find($item['producto_id'])->increment('cantidad', $item['cantidad']);

                // C) REGISTRO EN EL KARDEX (INVENTARIO)
                $movimiento = new Inventario();
                $movimiento->producto_id = $item['producto_id'];
                $movimiento->tipo_movimiento_inventario_id = $tipoMovCompra;
                $movimiento->cantidad = $item['cantidad'];
                $movimiento->tipo_referencia = 'Compra';
                $movimiento->referencia_id = $compra->id;
                $movimiento->motivo = "Ingreso por compra (Comprobante: {$compra->numero_comprobante})";
                $movimiento->usuario_id = Auth::id();
                $movimiento->estado = true; 
                $movimiento->save();

                // --- NUEVO: REGISTRO DE TRANSACCIÓN (FLUJO DE CAJA) ---
                // Asegúrate de usar las siglas correctas según tu Seeder (ej. 'COMPRA' o 'EGRESO')
                $tipoTransaccionCompra = TipoTransaccion::where('siglas', 'COM')->value('id');

                if (empty($tipoTransaccionCompra)) {
                    throw new \RuntimeException('No existe el tipo de transacción COM en la tabla tipo_transacciones.');
                }

                $transaccion = new Transaccion();
                $transaccion->tipo_transaccion_id = $tipoTransaccionCompra;
                $transaccion->tipo_referencia = 'Compra';
                $transaccion->referencia_id = $compra->id;
                $transaccion->monto = $compra->costo_total; // Asumiendo que tu columna se llama costo_total
                $transaccion->motivo = "Salida de dinero por Compra (Comprobante: {$compra->numero_comprobante})";
                $transaccion->usuario_id = Auth::id();
                $transaccion->estado = 1;
                $transaccion->save();
            }

            DB::commit();

            return response()->json([
                'codigo'  => 200,
                'mensaje' => 'Compra registrada correctamente',
                'compra'  => CompraResource::make($compra->load(['proveedor.persona', 'usuario', 'tipoDocumentoComprobante', 'detalles.producto'])),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'mensaje' => 'Error crítico al registrar la compra.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    #[OA\Post(
        path: '/api/compras/{id}',
        summary: 'Actualizar compra y detalles',
        description: 'Actualiza la cabecera de la compra, sincroniza los detalles (crea, actualiza o elimina) y recalcula automáticamente el stock de los productos. NOTA: Usa POST con _method=PUT debido a la subida de archivos.',
        tags: ['Compras'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID de la compra a actualizar',
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
                        'proveedor_id',
                        'tipo_documento_comprobante_id',
                        'numero_comprobante',
                        'costo_total',
                        'fecha_compra',
                        'estado',
                    ],
                    properties: [
                        new OA\Property(property: '_method', type: 'string', example: 'PUT', description: 'Spoofing del método'),
                        new OA\Property(property: 'proveedor_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_documento_comprobante_id', type: 'integer', example: 1),
                        new OA\Property(property: 'numero_comprobante', type: 'string', maxLength: 50, example: 'F001-000456'),
                        new OA\Property(property: 'costo_total', type: 'number', format: 'float', example: 1800.00),
                        new OA\Property(property: 'fecha_compra', type: 'string', format: 'date', example: '2026-07-30'),
                        new OA\Property(property: 'estado', type: 'integer', enum: [0, 1], example: 1, description: '0 = Inactivo, 1 = Activo'),
                        new OA\Property(
                            property: 'archivo_pdf', 
                            type: 'string', 
                            format: 'binary', 
                            description: 'Nuevo comprobante PDF (Opcional, reemplazará al anterior)'
                        ),
                        // Ejemplo de un detalle existente (con ID)
                        new OA\Property(property: 'detalles[0][id]', type: 'integer', example: 1, description: 'ID del detalle si ya existe (omitir si es nuevo)'),
                        new OA\Property(property: 'detalles[0][producto_id]', type: 'integer', example: 1),
                        new OA\Property(property: 'detalles[0][cantidad]', type: 'integer', example: 12),
                        new OA\Property(property: 'detalles[0][costo_unitario]', type: 'number', format: 'float', example: 100.00),                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Compra actualizada correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación'),
            new OA\Response(response: 404, description: 'Compra no encontrada'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]

    public function update(Request $request, Compra $compra)
    {
        try {
            $request->validate([
                'proveedor_id'                  => ['required', 'integer', 'exists:proveedores,id'],
                'tipo_documento_comprobante_id' => ['required', 'integer', 'exists:tipo_documento_comprobantes,id'],
                'numero_comprobante'            => ['required', 'string', 'max:50'],
                'costo_total'                   => ['required', 'numeric', 'min:0'],
                'fecha_compra'                  => ['required', 'date'],
                'estado'                        => ['required', 'boolean'],
                'archivo_pdf'                   => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
                
                'detalles'                      => ['required', 'array', 'min:1'],
                'detalles.*.id'                 => ['nullable', 'integer', 'exists:detalle_compras,id'], // ID opcional para saber si es nuevo o viejo
                'detalles.*.producto_id'        => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad'           => ['required', 'integer', 'min:1'],
                'detalles.*.costo_unitario'     => ['required', 'numeric', 'min:0'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors'  => $e->errors(),
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Actualizar PDF si existe
            if ($request->hasFile('archivo_pdf')) {
                if ($compra->ruta_pdf && \Illuminate\Support\Facades\Storage::disk('public')->exists($compra->ruta_pdf)) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($compra->ruta_pdf);
                }
                $compra->ruta_pdf = $request->file('archivo_pdf')->store('compras/comprobantes', 'public');
            }

            // 1. Recalcular el costo total
            $costoTotal = 0;
            foreach ($request->detalles as $item) {
                $costoTotal += ($item['cantidad'] * $item['costo_unitario']);
            }

            // 2. Actualizar cabecera
            $compra->proveedor_id = $request->proveedor_id;
            $compra->tipo_documento_comprobante_id = $request->tipo_documento_comprobante_id;
            $compra->numero_comprobante = $request->numero_comprobante;
            $compra->costo_total = $costoTotal;
            $compra->fecha_compra = $request->fecha_compra;
            $compra->estado = $request->estado;
            $compra->save();

            // 3. Gestión de Detalles y Stock
            $tipoMovCompra = TipoMovimientoInventario::where('siglas', 'COMPRA')->value('id');
            $tipoMovAjuste = TipoMovimientoInventario::where('siglas', 'AJUSTE')->value('id');

            $detallesEntrantes = collect($request->detalles);
            $idsEntrantes = $detallesEntrantes->pluck('id')->filter()->toArray();
            
            // 3.1 Eliminar detalles quitados por el usuario
            $detallesAEliminar = DetalleCompra::where('compra_id', $compra->id)
                                              ->whereNotIn('id', $idsEntrantes)
                                              ->get();

            foreach ($detallesAEliminar as $detalleViejo) {
                // Como ya NO compramos esto, lo QUITAMOS del inventario
                Producto::find($detalleViejo->producto_id)->decrement('cantidad', $detalleViejo->cantidad);
                
                // Registro Kardex (Ajuste negativo)
                $movimiento = new Inventario();
                $movimiento->producto_id = $detalleViejo->producto_id;
                $movimiento->tipo_movimiento_inventario_id = $tipoMovAjuste;
                $movimiento->cantidad = $detalleViejo->cantidad;
                $movimiento->tipo_referencia = 'Compra';
                $movimiento->referencia_id = $compra->id;
                $movimiento->motivo = "Ajuste negativo: Producto retirado tras eliminación en edición de la compra";
                $movimiento->usuario_id = Auth::id();
                $movimiento->estado = true;
                $movimiento->save();

                $detalleViejo->delete();
            }

            // 3.2 Actualizar y Crear nuevos detalles
            foreach ($request->detalles as $item) {
                $subtotalCalculado = $item['cantidad'] * $item['costo_unitario'];

                if (isset($item['id']) && $item['id']) {
                    $detalle = DetalleCompra::find($item['id']);
                    
                    if ($detalle->producto_id != $item['producto_id'] || $detalle->cantidad != $item['cantidad']) {
                        // PASO 1: Revertir la entrada vieja (QUITAR stock)
                        Producto::find($detalle->producto_id)->decrement('cantidad', $detalle->cantidad);
                        
                        $movAjuste = new Inventario();
                        $movAjuste->producto_id = $detalle->producto_id;
                        $movAjuste->tipo_movimiento_inventario_id = $tipoMovAjuste;
                        $movAjuste->cantidad = $detalle->cantidad;
                        $movAjuste->tipo_referencia = 'Compra';
                        $movAjuste->referencia_id = $compra->id;
                        $movAjuste->motivo = "Ajuste negativo por reversión previa a edición de compra";
                        $movAjuste->usuario_id = Auth::id();
                        $movAjuste->estado = true;
                        $movAjuste->save();

                        // PASO 2: Registrar la nueva entrada (SUMAR stock)
                        Producto::find($item['producto_id'])->increment('cantidad', $item['cantidad']);
                        
                        $movCompra = new Inventario();
                        $movCompra->producto_id = $item['producto_id'];
                        $movCompra->tipo_movimiento_inventario_id = $tipoMovCompra;
                        $movCompra->cantidad = $item['cantidad'];
                        $movCompra->tipo_referencia = 'Compra';
                        $movCompra->referencia_id = $compra->id;
                        $movCompra->motivo = "Ingreso corregido por edición de compra";
                        $movCompra->usuario_id = Auth::id();
                        $movCompra->estado = true;
                        $movCompra->save();
                    }

                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $item['cantidad'];
                    $detalle->costo_unitario = $item['costo_unitario'];
                    $detalle->subtotal = $subtotalCalculado;
                    $detalle->save();
                    
                } else {
                    // Detalle Nuevo
                    $detalle = new DetalleCompra();
                    $detalle->compra_id = $compra->id;
                    $detalle->producto_id = $item['producto_id'];
                    $detalle->cantidad = $item['cantidad'];
                    $detalle->costo_unitario = $item['costo_unitario'];
                    $detalle->subtotal = $subtotalCalculado;
                    $detalle->estado = true;
                    $detalle->save();

                    // SUMAMOS stock
                    Producto::find($item['producto_id'])->increment('cantidad', $item['cantidad']);

                    // Registro Kardex
                    $movNuevo = new Inventario();
                    $movNuevo->producto_id = $item['producto_id'];
                    $movNuevo->tipo_movimiento_inventarios_id = $tipoMovCompra;
                    $movNuevo->cantidad = $item['cantidad'];
                    $movNuevo->tipo_referencia = 'Compra';
                    $movNuevo->referencia_id = $compra->id;
                    $movNuevo->motivo = "Ingreso por producto agregado durante la edición de la compra";
                    $movNuevo->usuario_id = Auth::id();
                    $movNuevo->estado = true;
                    $movNuevo->save();

                    // Al final del método update, antes del DB::commit()
                    $transaccion = Transaccion::where('tipo_referencia', 'Compra')
                                                        ->where('referencia_id', $compra->id)
                                                        ->first();
                    if ($transaccion) {
                        $transaccion->monto = $compra->costo_total;
                        $transaccion->motivo = "Salida de dinero por Compra editada (Comprobante: {$compra->numero_comprobante})";
                        $transaccion->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'codigo'  => 200,
                'mensaje' => 'Compra actualizada correctamente',
                'compra'  => CompraResource::make($compra->load(['proveedor.persona', 'usuario', 'tipoDocumentoComprobante', 'detalles.producto'])),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'mensaje' => 'Error crítico al actualizar la compra.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}