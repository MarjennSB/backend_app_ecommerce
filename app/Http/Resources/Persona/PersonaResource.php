<?php

namespace App\Http\Resources\Persona;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'tipo_documento_identidad_id' => $this->tipo_documento_identidad_id,
            'tipo_documento_nombre'       => $this->tipoDocumentoIdentidad?->nombre,
            'numero_documento'            => $this->numero_documento,
            'nombres'                     => $this->nombres,
            'apellido_paterno'            => $this->apellido_paterno,
            'apellido_materno'            => $this->apellido_materno,
            'numero_celular'              => $this->numero_celular,
            'correo'                      => $this->correo,
            'direccion'                   => $this->direccion,
            'departamento_id'             => $this->departamento_id,
            'departamento_nombre'         => $this->departamento?->descripcion,
            'provincia_id'                => $this->provincia_id,
            'provincia_nombre'            => $this->provincia?->descripcion,
            'distrito_id'                 => $this->distrito_id,
            'distrito_nombre'             => $this->distrito?->descripcion,
            'estado'                      => $this->estado,
            'created_at'                  => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'                  => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
