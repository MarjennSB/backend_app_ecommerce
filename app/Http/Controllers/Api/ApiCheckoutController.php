<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Venta\VentaCollection;
use App\Http\Resources\Venta\VentaResource;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\Producto;
use App\Models\Inventario;
use App\Models\TipoMovimientoInventario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

class ApiCheckoutController extends Controller
{
    public function __construct()
    {
        // Solo usuarios logueados pueden hacer checkout y ver sus pedidos
        $this->middleware('jwt.auth');
    }

    #[OA\Get(
        path: '/api/checkout/mis-pedidos',
        summary: 'Listar los pedidos del cliente autenticado',
        description: 'Obtiene el historial de compras (pedidos) del usuario logueado.',
        tags: ['Checkout Web'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de pedidos obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);

        // Solo trae las ventas del usuario actual
        $ventas = Venta::with([
            'direccionEnvio',
            'tipoMetodoPago',
            'detalles.producto',
            'comprobanteVenta.tipoDocumentoComprobante'
        ])
            ->where('usuario_id', Auth::id())
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'pedidos' => VentaCollection::make($ventas),
            'pagination' => [
                'total' => $ventas->total(),
                'current_page' => $ventas->currentPage(),
                'last_page' => $ventas->lastPage(),
                'per_page' => $ventas->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/checkout',
        summary: 'Procesar un nuevo pedido (Checkout)',
        description: 'Genera una nueva venta en estado PENDIENTE desde el carrito del cliente de la tienda virtual. No genera comprobante ni transacción financiera aún.',
        tags: ['Checkout Web'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: [
                        'direccion_envio_id',
                        'tipo_metodo_pago_id',
                        'costo_envio',
                        'detalles'
                    ],
                    properties: [
                        new OA\Property(property: 'direccion_envio_id', type: 'integer', example: 1),
                        new OA\Property(property: 'tipo_metodo_pago_id', type: 'integer', example: 1),
                        new OA\Property(property: 'costo_envio', type: 'number', format: 'float', example: 15.00),
                        new OA\Property(
                            property: 'detalles',
                            type: 'array',
                            minItems: 1,
                            items: new OA\Items(
                                required: ['producto_id', 'cantidad'],
                                properties: [
                                    new OA\Property(property: 'producto_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'cantidad', type: 'integer', minimum: 1, example: 2)
                                ]
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Pedido registrado correctamente'),
            new OA\Response(response: 422, description: 'Errores de validación o stock insuficiente'),
            new OA\Response(response: 500, description: 'Error interno del servidor')
        ]
    )]
    public function store(Request $request)
    {
        try {
            $request->validate([
                'direccion_envio_id'  => ['required', 'integer', 'exists:direccion_envios,id'],
                'tipo_metodo_pago_id' => ['required', 'integer', 'exists:tipo_metodo_pagos,id'],
                'costo_envio'         => ['required', 'numeric', 'min:0'],
                
                'detalles'                   => ['required', 'array', 'min:1'],
                'detalles.*.producto_id'     => ['required', 'integer', 'exists:productos,id'],
                'detalles.*.cantidad'        => ['required', 'integer', 'min:1'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }

        // 1. Validar Stock de todos los productos y obtener sus precios actuales
        $erroresStock = [];
        $productosProcesados = [];
        
        foreach ($request->detalles as $index => $item) {
            $producto = Producto::find($item['producto_id']);

            if ($producto && $producto->stock_actual < $item['cantidad']) {
                $erroresStock["detalles.{$index}.cantidad"] = [
                    "Stock insuficiente para '{$producto->nombre}'. Disponible: {$producto->stock_actual}."
                ];
            } else {
                // Guardamos la info para no volver a consultar la BD
                $productosProcesados[] = [
                    'producto' => $producto,
                    'cantidad' => $item['cantidad'],
                    // Usamos el precio de oferta si existe, sino el precio de venta normal
                    'precio_unitario' => $producto->precio_oferta ?? $producto->precio_venta,
                ];
            }
        }

        if (!empty($erroresStock)) {
            return response()->json([
                'mensaje' => 'No hay stock suficiente para uno o más productos de tu carrito.',
                'errors' => $erroresStock,
            ], 422);
        }

        DB::beginTransaction();

        try {
            // 2. Calcular Totales
            $subtotal = 0;
            foreach ($productosProcesados as $item) {
                $subtotal += ($item['cantidad'] * $item['precio_unitario']);
            }

            $costoEnvio = $request->costo_envio;
            // Cálculo simple de IGV (18%) sobre el subtotal + envío.
            // Dependiendo de la regla de negocio, el IGV puede estar ya incluido en el precio. 
            // Asumiremos que el precio ya incluye IGV, por lo que desglosaremos el IGV referencial.
            $montoTotal = $subtotal + $costoEnvio;
            $igv = $montoTotal - ($montoTotal / 1.18); 

            // 3. Crear el Pedido (Venta en estado PENDIENTE)
            $venta = new Venta();
            $venta->usuario_id          = Auth::id();
            $venta->direccion_envio_id  = $request->direccion_envio_id;
            $venta->tipo_metodo_pago_id = $request->tipo_metodo_pago_id;
            $venta->subtotal            = $subtotal;
            $venta->descuento_total     = 0;
            $venta->costo_envio         = $costoEnvio;
            $venta->impuestos_igv       = round($igv, 2);
            $venta->monto_total         = round($montoTotal, 2);
            $venta->estado_venta        = 'PENDIENTE'; // IMPORTANTE: El comprobante se genera cuando pase a PAGADO
            $venta->fecha_venta         = now()->toDateString();
            $venta->estado              = 1;
            
            $venta->save();

            // 4. Obtener tipo de movimiento para descontar stock (Reserva por pedido)
            $tipoMovVenta = TipoMovimientoInventario::where('siglas', 'VENTA')->value('id');

            // 5. Guardar Detalles y actualizar inventario
            foreach ($productosProcesados as $item) {
                
                $detalle = new DetalleVenta();
                $detalle->venta_id             = $venta->id;
                $detalle->producto_id          = $item['producto']->id;
                $detalle->cantidad             = $item['cantidad'];
                $detalle->precio_unitario      = $item['precio_unitario'];
                $detalle->porcentaje_descuento = 0; // Se puede agregar lógica de cupones luego
                $detalle->subtotal             = $item['cantidad'] * $item['precio_unitario'];
                $detalle->estado               = 1;
                $detalle->save();

                // Descontar Stock (Reserva)
                $item['producto']->decrement('stock_actual', $item['cantidad']);

                // Registrar en Kardex
                if ($tipoMovVenta) {
                    $movimiento = new Inventario();
                    $movimiento->producto_id                   = $item['producto']->id;
                    $movimiento->tipo_movimiento_inventario_id = $tipoMovVenta;
                    $movimiento->cantidad                      = $item['cantidad'];
                    $movimiento->tipo_referencia               = 'Pedido Web';
                    $movimiento->referencia_id                 = $venta->id;
                    $movimiento->motivo                        = "Salida/Reserva por Pedido Web #{$venta->id}";
                    $movimiento->usuario_id                    = Auth::id();
                    $movimiento->estado                        = 1;
                    $movimiento->save();
                }
            }

            // NOTA: No generamos ComprobanteVenta ni Transaccion todavía. Eso lo hará un Webhook
            // o un método futuro de "confirmarPago" cuando la pasarela responda OK.

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Tu pedido ha sido procesado y está pendiente de pago.',
                'pedido' => VentaResource::make($venta->load(['detalles.producto', 'direccionEnvio'])),
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'mensaje' => 'Error crítico al registrar el pedido.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
