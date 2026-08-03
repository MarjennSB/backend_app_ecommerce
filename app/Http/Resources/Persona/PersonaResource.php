<?php

namespace App\Http\Resources\Persona;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return[
            'id'                          => $this->id,
            'tipo_documento_identidad_id' => $this->tipo_documento_identidad_id,
            'tipo_documento_identidad'    => $this->tipoDocumentoIdentidad?->nombre,
            'numero_documento'            => $this->numero_documento,
            'nombres'                     => $this->nombres,
            'apellido_paterno'            => $this->apellido_paterno,
            'apellido_materno'            => $this->apellido_materno,
            'correo'                      => $this->correo,
            'numero_celular'              => $this->numero_celular,
            'direccion'                   => $this->direccion,
            'departamento_id'             => $this->departamento_id,
            'departamento'                => $this->departamento?->descripcion,
            'provincia_id'                => $this->provincia_id,
            'provincia'                   => $this->provincia?->descripcion,
            'distrito_id'                 => $this->distrito_id,
            'distrito'                    => $this->distrito?->descripcion,
            'fecha_nacimiento'            => $this->fecha_nacimiento,
            'genero_id'                   => $this->genero_id,
            'genero'                      => $this->genero?->nombre,
            'estado'                      => $this->resource->estado,
            'created_at'                  => $this->resource->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}