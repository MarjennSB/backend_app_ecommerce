<?php

namespace App\Http\Resources\Carrito;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarritoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            'usuario_id' => $this->usuario_id,
            'usuario_correo' => $this->usuario?->correo,

            'estado' => $this->estado,

            'detalles' => $this->whenLoaded('detalles', function () {
                return $this->detalles->map(function ($detalle) {
                    return [
                        'id' => $detalle->id,

                        'carrito_id' => $detalle->carrito_id,
                        'producto_id' => $detalle->producto_id,

                        'cantidad' => $detalle->cantidad,

                        'producto' => $detalle->producto ? [
                            'id' => $detalle->producto->id,
                            'nombre' => $detalle->producto->nombre,
                            'slug' => $detalle->producto->slug,

                            'descripcion_corta' =>
                                $detalle->producto->descripcion_corta,

                            'precio_venta' =>
                                $detalle->producto->precio_venta,

                            'precio_oferta' =>
                                $detalle->producto->precio_oferta,

                            'stock_actual' =>
                                $detalle->producto->stock_actual,

                            'estado' =>
                                $detalle->producto->estado,

                            'categoria_id' =>
                                $detalle->producto->categoria_id,

                            'categoria_nombre' =>
                                $detalle->producto->categoria?->nombre,

                            'tipo_marca_id' =>
                                $detalle->producto->tipo_marca_id,

                            'marca_nombre' =>
                                $detalle->producto->tipoMarca?->nombre,

                            'imagenes' => $detalle->producto->imagenes
                                ? $detalle->producto->imagenes->map(function ($imagen) {
                                    return [
                                        'id' => $imagen->id,
                                        'ruta_imagen' => url(
                                            'storage/' . $imagen->ruta_imagen
                                        ),
                                    ];
                                })
                                : [],
                        ] : null,

                        'created_at' =>
                            $detalle->created_at?->format('Y-m-d H:i:s'),

                        'updated_at' =>
                            $detalle->updated_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            'created_at' =>
                $this->created_at?->format('Y-m-d H:i:s'),

            'updated_at' =>
                $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
