<?php

namespace App\Http\Resources\Persona;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // =========================
            // DATOS DE PERSONA
            // =========================
            'tipo_documento_identidad_id' => $this->tipo_documento_identidad_id,
            'tipo_documento_identidad_nombre' => $this->tipoDocumentoIdentidad?->nombre,
            'numero_documento' => $this->numero_documento,

            'nombres' => $this->nombres,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'numero_celular' => $this->numero_celular,

            'departamento_id' => $this->departamento_id,
            'departamento_nombre' => $this->departamento?->nombre,

            'provincia_id' => $this->provincia_id,
            'provincia_nombre' => $this->provincia?->nombre,

            'distrito_id' => $this->distrito_id,
            'distrito_nombre' => $this->distrito?->nombre,

            'fecha_nacimiento' => $this->fecha_nacimiento,
            'genero_id' => $this->genero_id,
            'genero_nombre' => $this->genero?->nombre,

            'profile_photo_path' => $this->profile_photo_path,

            // =========================
            // DATOS DE USUARIO
            // =========================
            'usuario_id' => $this->usuario?->id,
            'correo' => $this->usuario?->correo,
            'login' => $this->usuario?->login,
            'rol_id' => $this->usuario?->rol_id,
            'rol_nombre' => $this->usuario?->rol?->name,
            'estado' => $this->estado,

            'created_at' => $this->created_at,
        ];
    }
}
