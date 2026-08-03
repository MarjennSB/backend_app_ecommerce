<?php

namespace App\Http\Resources\Usuario;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsuarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'nombres'                     => $this->nombres,
            'apellido_paterno'            => $this->apellido_paterno,
            'apellido_materno'            => $this->apellido_materno,
            'correo'                      => $this->correo,
            'tipo_documento_identidad_id' => $this->tipo_documento_identidad_id,
            'tipo_documento_identidad'    => $this->tipoDocumentoIdentidad?->nombre,
            'numero_documento'            => $this->numero_documento,
            'login'                       => $this->login,
            'estado'                      => $this->estado,
            'rol_id'                      => $this->roles->first()?->id,
            'rol_nombre'                  => $this->roles->first()?->name,
            'genero_id'                   => $this->genero_id,
            'genero_nombre'               => $this->genero?->nombre,
            'profile_photo_path'          => $this->profile_photo_path,
            'created_at'                  => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
