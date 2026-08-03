<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Http\Resources\Producto\ProductoCollection;
use App\Models\Producto;
use Illuminate\Http\Request;
use App\Http\Resources\Producto\ProductoResource;
use Illuminate\Validation\ValidationException;
use App\Models\ImagenProducto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorPNG;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use OpenApi\Attributes as OA;

class ApiProductoController extends Controller
{
    public function __construct()
    {
        $this->middleware('jwt.auth');
        $this->middleware('can:listar_producto')->only('index');
        $this->middleware('can:registrar_producto')->only('store');
        $this->middleware('can:editar_producto')->only('update');
        $this->middleware('can:eliminar_producto')->only('destroy');
    }

    #[OA\Get(
        path: '/api/productos',
        summary: 'Listar productos',
        description: 'Obtiene una lista paginada de productos. Permite filtrar los resultados mediante un término de búsqueda (coincidencia parcial en nombre o descripción).',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda para filtrar por nombre o descripción del producto',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'laptop'
                )
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página (por defecto: 10)',
                schema: new OA\Schema(
                    type: 'integer',
                    default: 10,
                    example: 15
                )
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de productos obtenida correctamente'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            )
        ]
    )]
    

    public function index(Request $request)
    {
        $search = $request->input('search');
        $per_page = $request->input('per_page', 10);

        $productos = Producto::with(['usuario', 'categoria'])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    // Buscar en productos
                    $q->where('nombre', 'ilike', "%{$search}%")
                    ->orWhere('descripcion', 'ilike', "%{$search}%");

                });
            })
            ->orderByDesc('id')
            ->paginate($per_page);

        return response()->json([
            'productos' => ProductoCollection::make($productos),
            'total' => $productos->total(),
            'pagination' => [
                'total' => $productos->total(),
                'current_page' => $productos->currentPage(),
                'last_page' => $productos->lastPage(),
                'per_page' => $productos->perPage(),
                'total_visible' => min($productos->lastPage(), 5),
                'itemsPerPage' => $productos->perPage(),
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/productos',
        summary: 'Registrar producto',
        description: 'Crea un nuevo producto, genera automáticamente su código de barras (Code 128) y código QR, y permite subir múltiples imágenes asociadas.',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'categoria_id',
                        'nombre',
                        'descripcion',
                        'precio',
                        'cantidad',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: 'categoria_id',
                            type: 'integer',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'nombre',
                            type: 'string',
                            maxLength: 60,
                            example: 'Laptop HP Envy'
                        ),
                        new OA\Property(
                            property: 'descripcion',
                            type: 'string',
                            maxLength: 100,
                            example: 'Laptop potente ideal para desarrollo de software.'
                        ),
                        new OA\Property(
                            property: 'precio',
                            type: 'number',
                            format: 'float',
                            example: 3500.50
                        ),
                        new OA\Property(
                            property: 'cantidad',
                            type: 'integer',
                            example: 15
                        ),
                        new OA\Property(
                            property: 'fecha_vencimiento',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '2027-12-31'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: '1 para activo, 0 para inactivo'
                        ),
                        new OA\Property(
                            property: 'imagenes[]',
                            type: 'array',
                            description: 'Arreglo de imágenes del producto (jpeg, png, jpg, webp - máximo 2MB por archivo)',
                            items: new OA\Items(
                                type: 'string',
                                format: 'binary'
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Producto creado correctamente'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al generar códigos o subir archivos'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {

            $request->validate([
                'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
                'nombre' => ['required', 'string', 'max:60'],
                'descripcion' => ['required', 'string', 'max:100'],
                'precio' => ['required', 'numeric'],
                'cantidad' => ['required', 'integer'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'estado' => ['required', 'boolean'],
                'imagenes' => ['nullable', 'array'],
                'imagenes.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            ], [
                'categoria_id.required' => 'La categoría es obligatoria.',
                'categoria_id.exists' => 'La categoría seleccionada no existe.',

                'nombre.required' => 'El nombre del producto es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 60 caracteres.',

                'descripcion.required' => 'La descripción es obligatoria.',

                'precio.required' => 'El precio es obligatorio.',
                'precio.numeric' => 'El precio debe ser numérico.',

                'cantidad.required' => 'La cantidad es obligatoria.',

                'estado.required' => 'El estado es obligatorio.',

                'imagenes.*.image' => 'El archivo debe ser una imagen.',
                'imagenes.*.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
                'imagenes.*.max' => 'La imagen no debe superar los 2MB.',
            ]);

        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }


        DB::beginTransaction();

        try {


            /*
            |--------------------------------------------------------------------------
            | Generar código de barras único
            |--------------------------------------------------------------------------
            */

            do {

                $codigoBarra = rand(100000000000, 999999999999);

            } while (
                Producto::where('codigo_barras', $codigoBarra)->exists()
            );


            $barcodeGenerator = new BarcodeGeneratorPNG();

            $barcode = $barcodeGenerator->getBarcode(
                $codigoBarra,
                BarcodeGeneratorPNG::TYPE_CODE_128
            );


            $rutaCodigoBarra = "productos/codigos_barras/{$codigoBarra}.png";


            Storage::disk('public')->put(
                $rutaCodigoBarra,
                $barcode
            );


            /*
            |--------------------------------------------------------------------------
            | Crear producto
            |--------------------------------------------------------------------------
            */

            $producto = new Producto();

            $producto->usuario_id = Auth::id();
            $producto->categoria_id = $request->categoria_id;
            $producto->nombre = $request->nombre;
            $producto->descripcion = $request->descripcion;
            $producto->precio = $request->precio;
            $producto->cantidad = $request->cantidad;
            $producto->codigo_barras = $rutaCodigoBarra;
            $producto->fecha_vencimiento = $request->fecha_vencimiento;
            $producto->estado = $request->estado;

            $producto->save();



            /*
            |--------------------------------------------------------------------------
            | Generar código QR
            |--------------------------------------------------------------------------
            */

            $contenidoQr = json_encode([
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $codigoBarra,
            ]);


            $rutaCodigoQr = "productos/codigos_qr/producto_{$producto->id}.png";


            $renderer = new ImageRenderer(
                new RendererStyle(300),
                new ImagickImageBackEnd()
            );

            $writer = new Writer($renderer);

            $qr = $writer->writeString($contenidoQr);

            Storage::disk('public')->put(
                $rutaCodigoQr,
                $qr
            );


            $producto->codigo_qr = $rutaCodigoQr;

            $producto->save();



            /*
            |--------------------------------------------------------------------------
            | Guardar imágenes del producto
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('imagenes')) {

                foreach ($request->file('imagenes') as $imagen) {


                    $rutaImagen = $imagen->store(
                        'productos/imagenes',
                        'public'
                    );


                    $imagenProducto = new ImagenProducto();

                    $imagenProducto->producto_id = $producto->id;
                    $imagenProducto->ruta_imagen = $rutaImagen;
                    $imagenProducto->estado = true;

                    $imagenProducto->save();

                }

            }


            DB::commit();


            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Producto creado correctamente',
                'producto' => ProductoResource::make(
                    $producto->load(['usuario', 'categoria', 'imagenes'])
                ),
            ], 200);


        } catch (\Exception $e) {


            DB::rollBack();


            return response()->json([
                'mensaje' => 'Error al crear producto.',
                'error' => $e->getMessage(),
            ], 500);

        }
    }

    #[OA\Post(
        path: '/api/productos/{id}',
        summary: 'Actualizar producto',
        description: 'Actualiza los datos de un producto. Permite subir nuevas imágenes y/o eliminar imágenes existentes. NOTA: Debido al envío de archivos, se usa POST con el campo _method=PUT.',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID del producto a actualizar',
                schema: new OA\Schema(
                    type: 'integer',
                    example: 1
                )
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    required: [
                        'categoria_id',
                        'nombre',
                        'descripcion',
                        'precio',
                        'cantidad',
                        'estado'
                    ],
                    properties: [
                        new OA\Property(
                            property: '_method',
                            type: 'string',
                            example: 'PUT',
                            description: 'Spoofing del método para que Laravel procese las imágenes correctamente'
                        ),
                        new OA\Property(
                            property: 'categoria_id',
                            type: 'integer',
                            example: 1
                        ),
                        new OA\Property(
                            property: 'nombre',
                            type: 'string',
                            maxLength: 60,
                            example: 'Laptop HP Envy Editado'
                        ),
                        new OA\Property(
                            property: 'descripcion',
                            type: 'string',
                            maxLength: 100,
                            example: 'Laptop potente ideal para desarrollo de software (Actualizado).'
                        ),
                        new OA\Property(
                            property: 'precio',
                            type: 'number',
                            format: 'float',
                            example: 3600.50
                        ),
                        new OA\Property(
                            property: 'cantidad',
                            type: 'integer',
                            example: 18
                        ),
                        new OA\Property(
                            property: 'fecha_vencimiento',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '2027-12-31'
                        ),
                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: '1 para activo, 0 para inactivo'
                        ),
                        new OA\Property(
                            property: 'imagenes[]',
                            type: 'array',
                            description: 'Arreglo de NUEVAS imágenes a agregar (jpeg, png, jpg, webp - max 2MB)',
                            items: new OA\Items(
                                type: 'string',
                                format: 'binary'
                            )
                        ),
                        new OA\Property(
                            property: 'imagenes_eliminar[]',
                            type: 'array',
                            description: 'Arreglo con los IDs de las imágenes existentes que se desean eliminar de la base de datos y del servidor',
                            items: new OA\Items(
                                type: 'integer',
                                example: 5
                            )
                        )
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Producto actualizado correctamente'
            ),
            new OA\Response(
                response: 404,
                description: 'Producto no encontrado'
            ),
            new OA\Response(
                response: 422,
                description: 'Errores de validación'
            ),
            new OA\Response(
                response: 500,
                description: 'Error interno al actualizar o manejar archivos'
            ),
            new OA\Response(
                response: 401,
                description: 'No autorizado'
            )
        ]
    )]

    public function update(Request $request, Producto $producto)
    {
        try {

            $request->validate([
                'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
                'nombre' => ['required', 'string', 'max:60'],
                'descripcion' => ['required', 'string', 'max:100'],
                'precio' => ['required', 'numeric'],
                'cantidad' => ['required', 'integer'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'estado' => ['required', 'boolean'],

                'imagenes' => ['nullable', 'array'],
                'imagenes.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],

                'imagenes_eliminar' => ['nullable', 'array'],
                'imagenes_eliminar.*' => ['integer', 'exists:imagen_productos,id'],

            ], [

                'categoria_id.required' => 'La categoría es obligatoria.',
                'categoria_id.exists' => 'La categoría seleccionada no existe.',

                'nombre.required' => 'El nombre del producto es obligatorio.',
                'descripcion.required' => 'La descripción es obligatoria.',

                'precio.required' => 'El precio es obligatorio.',
                'precio.numeric' => 'El precio debe ser numérico.',

                'cantidad.required' => 'La cantidad es obligatoria.',

                'estado.required' => 'El estado es obligatorio.',

                'imagenes.*.image' => 'El archivo debe ser una imagen.',
                'imagenes.*.mimes' => 'La imagen debe ser JPG, PNG o WEBP.',
                'imagenes.*.max' => 'La imagen no debe superar los 2MB.',

                'imagenes_eliminar.*.exists' => 'La imagen seleccionada no existe.',
            ]);


        } catch (ValidationException $e) {

            return response()->json([
                'mensaje' => 'Errores de validación',
                'errors' => $e->errors(),
            ], 422);
        }


        DB::beginTransaction();

        try {


            /*
            |--------------------------------------------------------------------------
            | Actualizar datos del producto
            |--------------------------------------------------------------------------
            */

            $producto->categoria_id = $request->categoria_id;
            $producto->nombre = $request->nombre;
            $producto->descripcion = $request->descripcion;
            $producto->precio = $request->precio;
            $producto->cantidad = $request->cantidad;
            $producto->fecha_vencimiento = $request->fecha_vencimiento;
            $producto->estado = $request->estado;

            $producto->save();



            /*
            |--------------------------------------------------------------------------
            | Eliminar imágenes seleccionadas
            |--------------------------------------------------------------------------
            */

            if ($request->filled('imagenes_eliminar')) {

                $imagenesEliminar = ImagenProducto::whereIn(
                    'id',
                    $request->imagenes_eliminar
                )
                ->where('producto_id', $producto->id)
                ->get();


                foreach ($imagenesEliminar as $imagen) {


                    if (
                        $imagen->ruta_imagen &&
                        Storage::disk('public')->exists($imagen->ruta_imagen)
                    ) {

                        Storage::disk('public')->delete(
                            $imagen->ruta_imagen
                        );

                    }


                    $imagen->delete();

                }

            }



            /*
            |--------------------------------------------------------------------------
            | Agregar nuevas imágenes
            |--------------------------------------------------------------------------
            */

            if ($request->hasFile('imagenes')) {


                foreach ($request->file('imagenes') as $imagen) {


                    $rutaImagen = $imagen->store(
                        'productos/imagenes',
                        'public'
                    );


                    $imagenProducto = new ImagenProducto();

                    $imagenProducto->producto_id = $producto->id;
                    $imagenProducto->ruta_imagen = $rutaImagen;
                    $imagenProducto->estado = true;

                    $imagenProducto->save();

                }

            }



            DB::commit();


            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Producto actualizado correctamente',
                'producto' => ProductoResource::make(
                    $producto->load(['usuario', 'categoria', 'imagenes'])
                ),
            ], 200);



        } catch (\Exception $e) {


            DB::rollBack();


            return response()->json([
                'mensaje' => 'Error al actualizar producto.',
                'error' => $e->getMessage(),
            ], 500);

        }
    }
}