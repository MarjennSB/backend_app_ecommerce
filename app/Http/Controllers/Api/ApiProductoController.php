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
        $this->middleware('jwt.auth')->except(['index', 'show']);
        // Se removió 'can:listar_producto' del index para que sea público
        $this->middleware('can:registrar_producto')->only('store');
        $this->middleware('can:editar_producto')->only('update');
        /* $this->middleware('can:eliminar_producto')->only('destroy'); */
    }

    #[OA\Get(
        path: '/api/productos',
        summary: 'Listar productos',
        description: 'Obtiene una lista paginada de productos. Permite buscar por nombre, slug, marca, descripción corta, descripción larga o código de barras.',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],

        parameters: [

            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Término de búsqueda por nombre, slug, marca, descripción corta, descripción larga o código de barras.',
                schema: new OA\Schema(
                    type: 'string',
                    example: 'laptop'
                )
            ),

            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                required: false,
                description: 'Cantidad de registros a mostrar por página.',
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
        $categoria_id = $request->input('categoria_id');

        $productos = Producto::with([
            'usuario',
            'categoria',
            'marca',
            'imagenes',
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    $q->where('nombre', 'ilike', "%{$search}%")
                        ->orWhere('slug', 'ilike', "%{$search}%")
                        ->orWhere('descripcion_corta', 'ilike', "%{$search}%")
                        ->orWhere('descripcion_larga', 'ilike', "%{$search}%")
                        ->orWhere('codigo_barras', 'ilike', "%{$search}%")
                        ->orWhereHas('marca', function ($marcaQuery) use ($search) {
                            $marcaQuery->where('nombre', 'ilike', "%{$search}%");
                        });

                });
            })
            ->when($categoria_id, function ($query) use ($categoria_id) {
                $query->where('categoria_id', $categoria_id);
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

    #[OA\Get(
        path: '/api/productos/{slug}',
        summary: 'Obtener detalle de un producto por slug',
        description: 'Obtiene la ficha completa de un producto por su slug (público).',
        tags: ['Productos'],
        parameters: [
            new OA\Parameter(
                name: 'slug',
                in: 'path',
                required: true,
                description: 'Slug único del producto',
                schema: new OA\Schema(type: 'string', example: 'laptop-hp-envy')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Producto encontrado'),
            new OA\Response(response: 404, description: 'Producto no encontrado')
        ]
    )]
    public function show(Producto $producto)
    {
        $producto->load(['imagenes', 'categoria', 'marca', 'usuario']);
        return ProductoResource::make($producto);
    }


    #[OA\Post(
        path: '/api/productos',
        summary: 'Registrar producto',
        description: 'Crea un nuevo producto, genera automáticamente su código de barras y código QR, y permite subir múltiples imágenes asociadas.',
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
                        'slug',
                        'precio_venta',
                        'es_destacado',
                        'stock_actual',
                        'estado'
                    ],

                    properties: [

                        new OA\Property(
                            property: 'marca_id',
                            type: 'integer',
                            nullable: true,
                            example: 1,
                            description: 'ID del tipo de marca del producto'
                        ),

                        new OA\Property(
                            property: 'categoria_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID de la categoría del producto'
                        ),

                        new OA\Property(
                            property: 'nombre',
                            type: 'string',
                            maxLength: 150,
                            example: 'Laptop HP Envy',
                            description: 'Nombre del producto'
                        ),

                        new OA\Property(
                            property: 'slug',
                            type: 'string',
                            maxLength: 200,
                            example: 'laptop-hp-envy',
                            description: 'Slug único del producto'
                        ),

                        new OA\Property(
                            property: 'descripcion_corta',
                            type: 'string',
                            maxLength: 255,
                            nullable: true,
                            example: 'Laptop potente para desarrollo de software.',
                            description: 'Descripción corta del producto'
                        ),

                        new OA\Property(
                            property: 'descripcion_larga',
                            type: 'string',
                            nullable: true,
                            example: 'Laptop de alto rendimiento ideal para programación, diseño y desarrollo de software.',
                            description: 'Descripción detallada del producto'
                        ),

                        new OA\Property(
                            property: 'precio_venta',
                            type: 'number',
                            format: 'float',
                            example: 3500.50,
                            description: 'Precio de venta del producto'
                        ),

                        new OA\Property(
                            property: 'precio_oferta',
                            type: 'number',
                            format: 'float',
                            nullable: true,
                            example: 3299.90,
                            description: 'Precio de oferta del producto'
                        ),

                        new OA\Property(
                            property: 'precio_compra_referencial',
                            type: 'number',
                            format: 'float',
                            nullable: true,
                            example: 2800.00,
                            description: 'Precio de compra referencial'
                        ),

                        new OA\Property(
                            property: 'es_destacado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Indica si el producto es destacado: 1 sí, 0 no'
                        ),

                        new OA\Property(
                            property: 'stock_actual',
                            type: 'integer',
                            minimum: 0,
                            example: 15,
                            description: 'Stock actual del producto'
                        ),

                        new OA\Property(
                            property: 'fecha_vencimiento',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '2027-12-31',
                            description: 'Fecha de vencimiento del producto'
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado del producto: 1 activo, 0 inactivo'
                        ),

                        new OA\Property(
                            property: 'imagenes[]',
                            type: 'array',
                            nullable: true,
                            description: 'Arreglo de imágenes del producto. Formatos permitidos: JPG, JPEG, PNG y WEBP. Máximo 2MB por imagen.',
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
                response: 401,
                description: 'No autorizado'
            ),

            new OA\Response(
                response: 500,
                description: 'Error interno al generar códigos o subir archivos'
            )
        ]
    )]

    public function store(Request $request)
    {
        try {

            $request->validate([
                'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
                'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
                'nombre' => ['required', 'string', 'max:150'],
                'slug' => ['required', 'string', 'max:200', 'unique:productos,slug'],
                'descripcion_corta' => ['nullable', 'string', 'max:255'],
                'descripcion_larga' => ['nullable', 'string'],
                'precio_venta' => ['required', 'numeric', 'min:0'],
                'precio_oferta' => ['nullable', 'numeric', 'min:0'],
                'precio_compra_referencial' => ['nullable', 'numeric', 'min:0'],
                'es_destacado' => ['required', 'boolean'],
                'stock_actual' => ['required', 'integer', 'min:0'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'estado' => ['required', 'boolean'],
                'imagenes' => ['nullable', 'array'],
                'imagenes.*' => [
                    'image',
                    'mimes:jpeg,png,jpg,webp',
                    'max:2048'
                ],

            ], [

                'categoria_id.required' => 'La categoría es obligatoria.',
                'categoria_id.exists' => 'La categoría seleccionada no existe.',
                'marca_id.exists' => 'La marca seleccionada no existe.',                
                'nombre.required' => 'El nombre del producto es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
                'slug.required' => 'El slug del producto es obligatorio.',
                'slug.max' => 'El slug no puede superar los 200 caracteres.',
                'slug.unique' => 'Ese slug ya está en uso.',
                'descripcion_corta.max' => 'La descripción corta no puede superar los 255 caracteres.',
                'precio_venta.required' => 'El precio de venta es obligatorio.',
                'precio_venta.numeric' => 'El precio de venta debe ser numérico.',
                'precio_venta.min' => 'El precio de venta no puede ser negativo.',
                'precio_oferta.numeric' => 'El precio de oferta debe ser numérico.',
                'precio_oferta.min' => 'El precio de oferta no puede ser negativo.',
                'precio_compra_referencial.numeric' => 'El precio de compra referencial debe ser numérico.',
                'precio_compra_referencial.min' => 'El precio de compra referencial no puede ser negativo.',
                'es_destacado.required' => 'Debe indicar si el producto es destacado.',
                'stock_actual.required' => 'El stock actual es obligatorio.',
                'stock_actual.integer' => 'El stock actual debe ser un número entero.',
                'stock_actual.min' => 'El stock actual no puede ser negativo.',
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

                $codigoBarra = random_int(
                    100000000000,
                    999999999999
                );

            } while (
                Producto::where(
                    'codigo_barras',
                    'productos/codigos_barras/' . $codigoBarra . '.png'
                )->exists()
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
            $producto->marca_id = $request->marca_id;
            $producto->categoria_id = $request->categoria_id;

            $producto->nombre = $request->nombre;
            $producto->slug = $request->slug;

            $producto->descripcion_corta = $request->descripcion_corta;
            $producto->descripcion_larga = $request->descripcion_larga;

            $producto->precio_venta = $request->precio_venta;
            $producto->precio_oferta = $request->precio_oferta;
            $producto->precio_compra_referencial = $request->precio_compra_referencial;

            $producto->es_destacado = $request->es_destacado;
            $producto->stock_actual = $request->stock_actual;

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
                    $producto->load([
                        'usuario',
                        'categoria',
                        'imagenes',
                        'marca'
                    ])
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
        path: '/api/productos/{producto}',
        summary: 'Actualizar producto',
        description: 'Actualiza un producto existente. Permite modificar sus datos, agregar nuevas imágenes y eliminar imágenes existentes. Debido al envío de archivos, se utiliza POST con _method=PUT.',
        tags: ['Productos'],
        security: [['bearerAuth' => []]],

        parameters: [
            new OA\Parameter(
                name: 'producto',
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
                        '_method',
                        'categoria_id',
                        'nombre',
                        'slug',
                        'precio_venta',
                        'es_destacado',
                        'stock_actual',
                        'estado'
                    ],

                    properties: [

                        new OA\Property(
                            property: '_method',
                            type: 'string',
                            example: 'PUT',
                            description: 'Laravel Method Spoofing para procesar la petición como PUT'
                        ),

                        new OA\Property(
                            property: 'marca_id',
                            type: 'integer',
                            nullable: true,
                            example: 1,
                            description: 'ID del tipo de marca del producto. Debe existir en tipo_marcas.'
                        ),

                        new OA\Property(
                            property: 'categoria_id',
                            type: 'integer',
                            example: 1,
                            description: 'ID de la categoría del producto. Debe existir en categorias.'
                        ),

                        new OA\Property(
                            property: 'nombre',
                            type: 'string',
                            maxLength: 150,
                            example: 'Laptop HP Envy Editada',
                            description: 'Nombre del producto.'
                        ),

                        new OA\Property(
                            property: 'slug',
                            type: 'string',
                            maxLength: 200,
                            example: 'laptop-hp-envy-editada',
                            description: 'Slug único del producto.'
                        ),

                        new OA\Property(
                            property: 'descripcion_corta',
                            type: 'string',
                            maxLength: 255,
                            nullable: true,
                            example: 'Laptop potente para desarrollo de software.'
                        ),

                        new OA\Property(
                            property: 'descripcion_larga',
                            type: 'string',
                            nullable: true,
                            example: 'Laptop de alto rendimiento ideal para programación y diseño.'
                        ),

                        new OA\Property(
                            property: 'precio_venta',
                            type: 'number',
                            format: 'float',
                            example: 3600.50,
                            description: 'Precio de venta del producto.'
                        ),

                        new OA\Property(
                            property: 'precio_oferta',
                            type: 'number',
                            format: 'float',
                            nullable: true,
                            example: 3400.00,
                            description: 'Precio de oferta del producto.'
                        ),

                        new OA\Property(
                            property: 'precio_compra_referencial',
                            type: 'number',
                            format: 'float',
                            nullable: true,
                            example: 2900.00,
                            description: 'Precio de compra referencial.'
                        ),

                        new OA\Property(
                            property: 'es_destacado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Indica si el producto es destacado: 1 sí, 0 no.'
                        ),

                        new OA\Property(
                            property: 'stock_actual',
                            type: 'integer',
                            minimum: 0,
                            example: 20,
                            description: 'Cantidad disponible del producto.'
                        ),

                        new OA\Property(
                            property: 'fecha_vencimiento',
                            type: 'string',
                            format: 'date',
                            nullable: true,
                            example: '2027-12-31',
                            description: 'Fecha de vencimiento del producto.'
                        ),

                        new OA\Property(
                            property: 'estado',
                            type: 'integer',
                            enum: [0, 1],
                            example: 1,
                            description: 'Estado del producto: 1 activo, 0 inactivo.'
                        ),

                        new OA\Property(
                            property: 'imagenes[]',
                            type: 'array',
                            nullable: true,
                            description: 'Nuevas imágenes que se agregarán al producto. Formatos permitidos: JPG, JPEG, PNG y WEBP. Máximo 2MB por imagen.',
                            items: new OA\Items(
                                type: 'string',
                                format: 'binary'
                            )
                        ),

                        new OA\Property(
                            property: 'imagenes_eliminar[]',
                            type: 'array',
                            nullable: true,
                            description: 'IDs de las imágenes existentes que se desean eliminar de la base de datos y del servidor.',
                            items: new OA\Items(
                                type: 'integer',
                                example: 1
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
                response: 401,
                description: 'No autorizado - Token inválido o ausente'
            ),

            new OA\Response(
                response: 500,
                description: 'Error interno al actualizar producto o manejar archivos'
            )
        ]
    )]

    public function update(Request $request, Producto $producto)
    {
        try {
            $request->validate([
                'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
                'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
                'nombre' => ['required', 'string', 'max:150'],
                'slug' => ['required', 'string', 'max:200', 'unique:productos,slug,' . $producto->id],
                'descripcion_corta' => ['nullable', 'string', 'max:255'],
                'descripcion_larga' => ['nullable', 'string'],
                'precio_venta' => ['required', 'numeric', 'min:0'],
                'precio_oferta' => ['nullable', 'numeric', 'min:0'],
                'precio_compra_referencial' => ['nullable', 'numeric', 'min:0'],
                'es_destacado' => ['required', 'boolean'],
                'stock_actual' => ['required', 'integer', 'min:0'],
                'fecha_vencimiento' => ['nullable', 'date'],
                'estado' => ['required', 'boolean'],
                'imagenes' => ['nullable', 'array'],
                'imagenes.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
                'imagenes_eliminar' => ['nullable', 'array'],
                'imagenes_eliminar.*' => ['integer', 'exists:imagen_productos,id'],
            ], [
                'marca_id.exists' => 'La marca seleccionada no existe.',
                'categoria_id.required' => 'La categoría es obligatoria.',
                'categoria_id.exists' => 'La categoría seleccionada no existe.',
                'nombre.required' => 'El nombre del producto es obligatorio.',
                'nombre.max' => 'El nombre no puede superar los 150 caracteres.',
                'slug.required' => 'El slug del producto es obligatorio.',
                'slug.max' => 'El slug no puede superar los 200 caracteres.',
                'slug.unique' => 'Ese slug ya está en uso.',
                'descripcion_corta.max' => 'La descripción corta no puede superar los 255 caracteres.',
                'precio_venta.required' => 'El precio de venta es obligatorio.',
                'precio_venta.numeric' => 'El precio de venta debe ser numérico.',
                'precio_venta.min' => 'El precio de venta no puede ser negativo.',
                'precio_oferta.numeric' => 'El precio de oferta debe ser numérico.',
                'precio_oferta.min' => 'El precio de oferta no puede ser negativo.',
                'precio_compra_referencial.numeric' => 'El precio de compra referencial debe ser numérico.',
                'precio_compra_referencial.min' => 'El precio de compra referencial no puede ser negativo.',
                'es_destacado.required' => 'Debe indicar si el producto es destacado.',
                'stock_actual.required' => 'El stock actual es obligatorio.',
                'stock_actual.integer' => 'El stock actual debe ser un número entero.',
                'stock_actual.min' => 'El stock actual no puede ser negativo.',
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
            $producto->marca_id = $request->marca_id;
            $producto->categoria_id = $request->categoria_id;
            $producto->nombre = $request->nombre;
            $producto->slug = $request->slug;
            $producto->descripcion_corta = $request->descripcion_corta;
            $producto->descripcion_larga = $request->descripcion_larga;
            $producto->precio_venta = $request->precio_venta;
            $producto->precio_oferta = $request->precio_oferta;
            $producto->precio_compra_referencial = $request->precio_compra_referencial;
            $producto->es_destacado = $request->es_destacado;
            $producto->stock_actual = $request->stock_actual;
            $producto->fecha_vencimiento = $request->fecha_vencimiento;
            $producto->estado = $request->estado;
            $producto->save();

            if ($request->filled('imagenes_eliminar')) {
                $imagenesEliminar = ImagenProducto::whereIn('id', $request->imagenes_eliminar)
                    ->where('producto_id', $producto->id)
                    ->get();

                foreach ($imagenesEliminar as $imagen) {
                    if ($imagen->ruta_imagen && Storage::disk('public')->exists($imagen->ruta_imagen)) {
                        Storage::disk('public')->delete($imagen->ruta_imagen);
                    }

                    $imagen->delete();
                }
            }

            if ($request->hasFile('imagenes')) {
                foreach ($request->file('imagenes') as $imagen) {
                    $imagenProducto = new ImagenProducto();
                    $imagenProducto->producto_id = $producto->id;
                    $imagenProducto->ruta_imagen = $imagen->store('productos/imagenes', 'public');
                    $imagenProducto->estado = true;
                    $imagenProducto->save();
                }
            }

            DB::commit();

            return response()->json([
                'codigo' => 200,
                'mensaje' => 'Producto actualizado correctamente',
                'producto' => ProductoResource::make(
                    $producto->load(['usuario', 'categoria', 'marca', 'imagenes'])
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