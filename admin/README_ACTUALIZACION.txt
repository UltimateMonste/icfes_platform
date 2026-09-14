# Studia360 — interfaz administrativa actualizada

Cambios principales:
- Remodelación visual de la interfaz administrativa con un estilo limpio y consistente.
- Materias: tarjetas compactas y navegación amigable.
- Temas: filtros por materia, grado y búsqueda; acceso directo a edición, contenido y vista previa.
- La edición de nombre, descripción, materia y grado se centraliza en `contenidos/informacion_tema.php`.
- `editar_tema_info.php` queda como compatibilidad y redirige al editor correcto.
- Vista previa administrativa corregida y con normalización del contenido HTML para evitar desbordamientos y distorsiones.
- Editor de contenido con controles de formato simplificados para evitar estilos pegados que rompan el diseño.
- Recuperación: el administrador restablece la contraseña únicamente al número de documento; no crea ni conoce contraseñas personalizadas.
- Comunicación: el menú se presenta como “Quejas, reclamos y recomendaciones”, usando el buzón existente.
- Se añadió `admin/assets/studia-admin.css` como hoja de estilos común.

No se requieren cambios SQL para estas modificaciones.
