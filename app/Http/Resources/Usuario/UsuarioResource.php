<?php

namespace App\Http\Resources\Usuario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'correo'            => $this->correo,
            'email_verified_at' => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'login'             => $this->login,

            'persona_id'        => $this->persona_id,
            'persona_nombre'    => $this->persona?->nombres,
            'persona_apellido_paterno' => $this->persona?->apellido_paterno,
            'persona_apellido_materno' => $this->persona?->apellido_materno,

            'rol_id'            => $this->rol_id,
            'rol_nombre'       => $this->roles->first()?->name,

            'estado'            => $this->estado,
            'created_at'        => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'        => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
