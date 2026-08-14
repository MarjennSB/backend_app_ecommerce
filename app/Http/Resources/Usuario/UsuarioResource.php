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
            'tipo_documento_identidad_id' => $this->tipo_documento_identidad_id,
            'numero_documento'            => $this->numero_documento,
            'nombres'                     => $this->nombres,
            'apellido_paterno'            => $this->apellido_paterno,
            'apellido_materno'            => $this->apellido_materno,
            'numero_celular'              => $this->numero_celular,
            'departamento_id'             => $this->departamento_id,
            'departamento_nombre'         => $this->departamento?->descripcion,
            'provincia_id'                => $this->provincia_id,
            'provincia_nombre'            => $this->provincia?->descripcion,
            'distrito_id'                 => $this->distrito_id,
            'distrito_nombre'             => $this->distrito?->descripcion,
            'fecha_nacimiento'            => $this->fecha_nacimiento,
            'genero_id'                   => $this->genero_id,
            'profile_photo_path'          => $this->profile_photo_path ? url('storage/' . $this->profile_photo_path) : null,
            'correo'                      => $this->correo,
            'email_verified_at'           => $this->email_verified_at?->format('Y-m-d H:i:s'),
            'rol_id'                      => $this->rol_id,
            'rol_nombre'                  => $this->roles->first()?->name,
            'acepto_termino_condiciones'  => $this->acepto_termino_condiciones,
            'estado'                      => $this->estado,
            'created_at'                  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'                  => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
