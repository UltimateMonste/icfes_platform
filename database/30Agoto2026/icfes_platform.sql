-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-08-2026 a las 18:54:36
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `icfes_platform`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avatares`
--

CREATE TABLE `avatares` (
  `id_avatar` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `puntos_requeridos` int(11) DEFAULT 0,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `avatares`
--

INSERT INTO `avatares` (`id_avatar`, `nombre`, `imagen`, `puntos_requeridos`, `estado`) VALUES
(1, 'Explorador', 'avatar1.png', 0, 'Activo'),
(2, 'Matemático', 'avatar2.png', 200, 'Activo'),
(3, 'Científico', 'avatar3.png', 500, 'Activo'),
(4, 'Investigador', 'avatar4.png', 900, 'Activo'),
(5, 'Maestro ICFES', 'avatar5.png', 1500, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `competencias`
--

CREATE TABLE `competencias` (
  `id_competencia` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `componentes`
--

CREATE TABLE `componentes` (
  `id_componente` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id_configuracion` int(11) NOT NULL,
  `nombre_colegio` varchar(200) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `correo_admin` varchar(120) DEFAULT NULL,
  `anio_lectivo` year(4) DEFAULT NULL,
  `color_principal` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id_configuracion`, `nombre_colegio`, `logo`, `correo_admin`, `anio_lectivo`, `color_principal`) VALUES
(1, 'Institución Educativa Departamental', NULL, 'admin@colegio.edu.co', '2025', '#0d6efd');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contenido_temas`
--

CREATE TABLE `contenido_temas` (
  `id_contenido` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL,
  `contenido` longtext NOT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `estado` enum('Publicado','Borrador') NOT NULL DEFAULT 'Publicado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `contenido_temas`
--

INSERT INTO `contenido_temas` (`id_contenido`, `id_tema`, `contenido`, `fecha_creacion`, `fecha_actualizacion`, `estado`) VALUES
(1, 19, '<p><a href=\"https://www.youtube.com/watch?v=6naSh-vJ6Gs\" target=\"_blank\">Hola</a><span style=\"font-family: &quot;Comic Sans MS&quot;;\">﻿</span><font face=\"Comic Sans MS\"># Biología Básica</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">La biología es la ciencia que estudia los seres vivos.</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">## ¿Qué estudia la biología?</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">La biología analiza:</font></p><p><font face=\"Comic Sans MS\">NONONO</font></p><p><font face=\"Comic Sans MS\">• Los organismos.</font></p><p><font face=\"Comic Sans MS\">• Sus estructuras.</font></p><p><font face=\"Comic Sans MS\">• Sus funciones.</font></p><p><font face=\"Comic Sans MS\">• Sus relaciones con el ambiente.</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">### Concepto importante</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">Los seres vivos realizan funciones vitales como nutrición, relación y reproducción.</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><img src=\"/icfes_platform/assets/uploads/contenidos/2e71b4e863f988ad4691d478845e560e_1787901241.jpg\" style=\"max-width: 100%; width: 50%;\" alt=\"IMG_0780.JPG\"><font face=\"Comic Sans MS\"><br></font></p>', '2026-08-28 05:55:57', '2026-08-29 06:22:45', 'Publicado'),
(2, 20, '<p>Ostia sisisi</p>', '2026-08-28 06:12:09', '2026-08-28 06:12:09', 'Publicado'),
(5, 47, '<p>Verbo to-be&nbsp;<br>ñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñ</p><div class=\"info-box\"><div class=\"bloque-label\">💡 CONCEPTO CLAVE</div><p>ñañañañañaña</p></div><p>ñiñiñiñiñiñiñiññiñiñiññiiññiññi</p>', '2026-08-30 14:43:01', '2026-08-30 14:43:01', 'Publicado');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id_curso` int(11) NOT NULL,
  `grado` enum('9','10','11') NOT NULL,
  `grupo` varchar(5) NOT NULL,
  `director` varchar(150) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id_curso`, `grado`, `grupo`, `director`, `estado`) VALUES
(10, '9', '901', NULL, 'Activo'),
(11, '10', '1001', NULL, 'Activo'),
(12, '11', '1101', NULL, 'Activo'),
(13, '11', '1102', NULL, 'Activo'),
(14, '9', '902', NULL, 'Activo'),
(15, '9', '903', NULL, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `evaluaciones`
--

CREATE TABLE `evaluaciones` (
  `id_evaluacion` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `id_tema` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `instrucciones` text DEFAULT NULL,
  `tipo` enum('Practica','Simulacro') NOT NULL,
  `grado` enum('9','10','11') NOT NULL,
  `tiempo_minutos` int(11) DEFAULT 30,
  `intentos_permitidos` int(11) DEFAULT 1,
  `puntaje_maximo` int(11) DEFAULT 100,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_puntos`
--

CREATE TABLE `historial_puntos` (
  `id_historial` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `puntos` int(11) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_puntos`
--

INSERT INTO `historial_puntos` (`id_historial`, `id_usuario`, `motivo`, `referencia`, `puntos`, `fecha`) VALUES
(1, 8, 'Tema completado: 8', NULL, 10, '2026-08-30 14:55:35'),
(2, 8, 'Tema completado: 36', NULL, 10, '2026-08-30 16:21:32'),
(3, 8, 'Tema completado: 26', NULL, 10, '2026-08-30 16:21:56'),
(4, 8, 'Tema completado: 25', NULL, 10, '2026-08-30 16:22:02'),
(5, 8, 'Tema completado: 27', NULL, 10, '2026-08-30 16:22:08'),
(6, 8, 'Tema completado: 43', NULL, 10, '2026-08-30 16:22:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insignias`
--

CREATE TABLE `insignias` (
  `id_insignia` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `criterio` varchar(200) DEFAULT NULL,
  `puntos_otorgados` int(11) DEFAULT 0,
  `estado` enum('Activa','Inactiva') DEFAULT 'Activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `instituciones`
--

CREATE TABLE `instituciones` (
  `id_institucion` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `municipio` varchar(100) NOT NULL,
  `departamento` varchar(100) NOT NULL,
  `estado` enum('Activa','Inactiva') DEFAULT 'Activa'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `instituciones`
--

INSERT INTO `instituciones` (`id_institucion`, `nombre`, `municipio`, `departamento`, `estado`) VALUES
(1, 'Institución Educativa Departamental', 'Por definir', 'Cundinamarca', 'Activa');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `intentos`
--

CREATE TABLE `intentos` (
  `id_intento` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_evaluacion` int(11) NOT NULL,
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `respuestas_correctas` int(11) DEFAULT 0,
  `respuestas_incorrectas` int(11) DEFAULT 0,
  `puntaje` decimal(5,2) DEFAULT NULL,
  `tiempo_empleado` int(11) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `navegador` varchar(150) DEFAULT NULL,
  `estado` enum('En proceso','Finalizado') DEFAULT 'En proceso'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `logs_sistema`
--

CREATE TABLE `logs_sistema` (
  `id_log` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `accion` varchar(255) DEFAULT NULL,
  `direccion_ip` varchar(45) DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `logs_sistema`
--

INSERT INTO `logs_sistema` (`id_log`, `id_usuario`, `accion`, `direccion_ip`, `fecha`) VALUES
(1, 2, 'Restableció la contraseña del estudiante ID 1', '::1', '2026-08-13 04:55:12'),
(2, 2, 'Restableció la contraseña del estudiante ID 1', '::1', '2026-08-16 02:19:35'),
(3, 2, 'Importación de estudiantes desde Excel. Importados: 3. Omitidos: 0. Errores: 0', '::1', '2026-08-16 04:17:24'),
(4, 2, 'Registro manual de estudiante. ID: 6', '::1', '2026-08-16 04:37:41'),
(5, 2, 'Restableció la contraseña del estudiante ID 1', '::1', '2026-08-27 03:31:14'),
(6, 2, 'Restableció la contraseña del estudiante ID 3', '::1', '2026-08-27 03:31:17');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `materias`
--

CREATE TABLE `materias` (
  `id_materia` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `materias`
--

INSERT INTO `materias` (`id_materia`, `nombre`, `descripcion`) VALUES
(1, 'Matemáticas', 'Desarrollo del pensamiento matemático, álgebra, geometría, estadística y resolución de problemas.'),
(2, 'Lectura Crítica', 'Comprensión, interpretación y análisis de textos.'),
(3, 'Ciencias Naturales', 'Biología, Física y Química aplicadas al entorno.'),
(4, 'Sociales y Ciudadanas', 'Historia, geografía, constitución política y competencias ciudadanas.'),
(5, 'Inglés', 'Comprensión y uso del idioma inglés según el Marco Común Europeo.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `niveles`
--

CREATE TABLE `niveles` (
  `id_nivel` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `puntos_minimos` int(11) DEFAULT NULL,
  `puntos_maximos` int(11) DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `niveles`
--

INSERT INTO `niveles` (`id_nivel`, `nombre`, `descripcion`, `puntos_minimos`, `puntos_maximos`, `imagen`) VALUES
(1, 'Iniciado', 'Has comenzado tu preparación para las pruebas ICFES.', 0, 199, 'nivel1.png'),
(2, 'Aprendiz', 'Estás fortaleciendo tus conocimientos y avanzando en tu preparación.', 200, 499, 'nivel2.png'),
(3, 'Explorador', 'Has avanzado y estás explorando nuevos conocimientos y desafíos.', 500, 899, 'nivel3.png'),
(4, 'Experto', 'Demuestras un buen dominio de los contenidos trabajados.', 900, 1499, 'nivel4.png'),
(5, 'Maestro ICFES', 'Has alcanzado un alto nivel de preparación y dominio.', 1500, 999999, 'nivel5.png');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `opciones`
--

CREATE TABLE `opciones` (
  `id_opcion` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `opcion` char(1) NOT NULL,
  `descripcion` text NOT NULL,
  `es_correcta` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `preguntas`
--

CREATE TABLE `preguntas` (
  `id_pregunta` int(11) NOT NULL,
  `id_evaluacion` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL,
  `enunciado` text NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `nivel` enum('Bajo','Medio','Alto') DEFAULT 'Medio',
  `id_competencia` int(11) DEFAULT NULL,
  `id_componente` int(11) DEFAULT NULL,
  `explicacion` text DEFAULT NULL,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `progreso`
--

CREATE TABLE `progreso` (
  `id_progreso` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL,
  `recursos_vistos` int(11) DEFAULT 0,
  `evaluaciones_realizadas` int(11) DEFAULT 0,
  `porcentaje_avance` decimal(5,2) DEFAULT 0.00,
  `completado` tinyint(1) NOT NULL DEFAULT 0,
  `ultima_actividad` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `progreso`
--

INSERT INTO `progreso` (`id_progreso`, `id_usuario`, `id_tema`, `recursos_vistos`, `evaluaciones_realizadas`, `porcentaje_avance`, `completado`, `ultima_actividad`, `fecha_completado`) VALUES
(1, 7, 19, 1, 0, 99.00, 0, '2026-08-30 11:52:28', NULL),
(3, 8, 7, 0, 0, 10.00, 0, '2026-08-30 09:55:24', NULL),
(4, 8, 8, 0, 0, 100.00, 0, '2026-08-30 09:55:35', NULL),
(5, 8, 9, 0, 0, 10.00, 0, '2026-08-30 09:55:50', NULL),
(6, 8, 16, 0, 0, 10.00, 0, '2026-08-30 09:56:07', NULL),
(7, 8, 17, 0, 0, 10.00, 0, '2026-08-30 09:56:10', NULL),
(8, 8, 18, 0, 0, 10.00, 0, '2026-08-30 09:56:12', NULL),
(9, 8, 25, 0, 0, 100.00, 0, '2026-08-30 11:22:02', NULL),
(10, 8, 26, 0, 0, 100.00, 0, '2026-08-30 11:21:56', NULL),
(11, 8, 27, 0, 0, 100.00, 0, '2026-08-30 11:22:08', NULL),
(12, 8, 35, 0, 0, 10.00, 0, '2026-08-30 10:08:57', NULL),
(13, 8, 34, 0, 0, 10.00, 0, '2026-08-30 10:09:05', NULL),
(14, 8, 43, 0, 0, 100.00, 0, '2026-08-30 11:22:15', NULL),
(15, 8, 44, 0, 0, 10.00, 0, '2026-08-30 11:17:14', NULL),
(16, 8, 45, 0, 0, 10.00, 0, '2026-08-30 11:17:17', NULL),
(17, 8, 36, 0, 0, 100.00, 0, '2026-08-30 11:21:32', NULL),
(18, 8, 19, 1, 0, 99.00, 0, '2026-08-30 11:38:30', NULL),
(19, 7, 20, 0, 0, 10.00, 0, '2026-08-30 11:52:48', NULL),
(20, 7, 21, 0, 0, 10.00, 0, '2026-08-30 11:52:53', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recursos`
--

CREATE TABLE `recursos` (
  `id_recurso` int(11) NOT NULL,
  `id_tema` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `tipo` enum('video','articulo','blog','app','pdf','juego','simulador','presentacion') NOT NULL,
  `url` varchar(500) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `fecha_publicacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `autor` varchar(150) DEFAULT NULL,
  `fuente` varchar(150) DEFAULT NULL,
  `visitas` int(11) DEFAULT 0,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recursos`
--

INSERT INTO `recursos` (`id_recurso`, `id_tema`, `titulo`, `tipo`, `url`, `descripcion`, `imagen`, `fecha_publicacion`, `autor`, `fuente`, `visitas`, `estado`) VALUES
(1, 1, 'Introducción al Álgebra', 'video', 'https://www.youtube.com/watch?v=example1', 'Conceptos básicos de álgebra para estudiantes de noveno', NULL, '2026-08-05 02:49:55', NULL, NULL, 0, 'Activo'),
(2, 1, 'Ejercicios de Álgebra', 'blog', 'https://www.superprof.co/blog/algebra-basica/', 'Ejercicios y explicaciones de álgebra', NULL, '2026-08-05 02:49:55', NULL, NULL, 0, 'Activo'),
(3, 2, 'Geometría Básica', 'video', 'https://www.youtube.com/watch?v=example2', 'Figuras geométricas y propiedades', NULL, '2026-08-05 02:49:55', NULL, NULL, 0, 'Activo'),
(4, 2, 'Guía de Geometría', 'pdf', 'https://ejemplo.com/geometria.pdf', 'Material de apoyo para geometría', NULL, '2026-08-05 02:49:55', NULL, NULL, 0, 'Activo'),
(7, 19, 'Minecraft', 'video', 'https://www.youtube.com/watch?v=oM9fUlGET-w', NULL, NULL, '2026-08-29 06:22:07', NULL, NULL, 0, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `respuestas_usuario`
--

CREATE TABLE `respuestas_usuario` (
  `id_respuesta` int(11) NOT NULL,
  `id_intento` int(11) NOT NULL,
  `id_pregunta` int(11) NOT NULL,
  `id_opcion` int(11) NOT NULL,
  `es_correcta` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Estudiante');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sugerencias`
--

CREATE TABLE `sugerencias` (
  `id_sugerencia` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `asunto` varchar(150) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `tipo` enum('Sugerencia','Queja','Felicitacion','Error') DEFAULT NULL,
  `estado` enum('Pendiente','Respondida','Cerrada') DEFAULT 'Pendiente',
  `respuesta` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temas`
--

CREATE TABLE `temas` (
  `id_tema` int(11) NOT NULL,
  `id_materia` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `contenido` longtext DEFAULT NULL,
  `grado` enum('9','10','11') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `temas`
--

INSERT INTO `temas` (`id_tema`, `id_materia`, `nombre`, `descripcion`, `contenido`, `grado`) VALUES
(1, 1, 'Álgebra', 'Expresiones algebraicas y ecuaciones', NULL, '9'),
(2, 1, 'Geometría', 'Figuras geométricas y propiedades', NULL, '9'),
(3, 1, 'Estadística', 'Recolección e interpretación de datos', NULL, '9'),
(4, 1, 'Funciones', 'Concepto y representación de funciones', NULL, '10'),
(5, 1, 'Probabilidad', 'Eventos y cálculo de probabilidades', NULL, '10'),
(6, 1, 'Geometría Analítica', 'Plano cartesiano y rectas', NULL, '10'),
(7, 1, 'Trigonometría', 'Razones trigonométricas y aplicaciones', NULL, '11'),
(8, 1, 'Cálculo Básico', 'Límites y derivadas introductorias', NULL, '11'),
(9, 1, 'Razonamiento Cuantitativo', 'Resolución de problemas tipo ICFES', NULL, '11'),
(10, 2, 'Comprensión Literal', 'Identificación de información explícita', NULL, '9'),
(11, 2, 'Comprensión Inferencial', 'Deducción de información implícita', NULL, '9'),
(12, 2, 'Tipos de Texto', 'Narrativo, expositivo y argumentativo', NULL, '9'),
(13, 2, 'Análisis de Textos', 'Interpretación y análisis textual', NULL, '10'),
(14, 2, 'Estructura Argumentativa', 'Tesis, argumentos y conclusiones', NULL, '10'),
(15, 2, 'Lectura Comparativa', 'Comparación entre textos', NULL, '10'),
(16, 2, 'Pensamiento Crítico', 'Evaluación crítica de contenidos', NULL, '11'),
(17, 2, 'Interpretación Avanzada', 'Análisis complejo de textos', NULL, '11'),
(18, 2, 'Competencias ICFES', 'Estrategias para preguntas Saber 11', NULL, '11'),
(19, 3, 'Biología Básica', 'Seres vivos y ecosistemas', '<p><a href=\"https://www.youtube.com/watch?v=6naSh-vJ6Gs\" target=\"_blank\">Hola</a><span style=\"font-family: &quot;Comic Sans MS&quot;;\">﻿</span><font face=\"Comic Sans MS\"># Biología Básica</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">La biología es la ciencia que estudia los seres vivos.</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">## ¿Qué estudia la biología?</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">La biología analiza:</font></p><p><font face=\"Comic Sans MS\">NONONO</font></p><p><font face=\"Comic Sans MS\">• Los organismos.</font></p><p><font face=\"Comic Sans MS\">• Sus estructuras.</font></p><p><font face=\"Comic Sans MS\">• Sus funciones.</font></p><p><font face=\"Comic Sans MS\">• Sus relaciones con el ambiente.</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">### Concepto importante</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><font face=\"Comic Sans MS\">Los seres vivos realizan funciones vitales como nutrición, relación y reproducción.</font></p><p><font face=\"Comic Sans MS\"><br></font></p><p><img src=\"/icfes_platform/assets/uploads/contenidos/2e71b4e863f988ad4691d478845e560e_1787901241.jpg\" style=\"max-width: 100%; width: 50%;\" alt=\"IMG_0780.JPG\"><font face=\"Comic Sans MS\"><br></font></p>', '9'),
(20, 3, 'Física Básica', 'Movimiento y energía', '<p>Ostia sisisi</p>', '9'),
(21, 3, 'Química Básica', 'Materia y sus propiedades', NULL, '9'),
(22, 3, 'Genética', 'Herencia y ADN', NULL, '10'),
(23, 3, 'Mecánica', 'Leyes del movimiento', NULL, '10'),
(24, 3, 'Enlaces Químicos', 'Tipos de enlaces y reacciones', NULL, '10'),
(25, 3, 'Ecología', 'Relación entre organismos y ambiente', NULL, '11'),
(26, 3, 'Electricidad y Magnetismo', 'Fenómenos eléctricos', NULL, '11'),
(27, 3, 'Química Orgánica', 'Compuestos del carbono', NULL, '11'),
(28, 4, 'Historia de Colombia', 'Principales procesos históricos', NULL, '9'),
(29, 4, 'Geografía de Colombia', 'Relieve, clima y regiones', NULL, '9'),
(30, 4, 'Participación Ciudadana', 'Derechos y deberes', NULL, '9'),
(31, 4, 'Constitución Política', 'Principios fundamentales', NULL, '10'),
(32, 4, 'Democracia y Estado', 'Organización política colombiana', NULL, '10'),
(33, 4, 'Globalización', 'Impacto mundial y local', NULL, '10'),
(34, 4, 'Competencias Ciudadanas', 'Resolución de conflictos y convivencia', NULL, '11'),
(35, 4, 'Conflicto Armado Colombiano', 'Contexto histórico y social', NULL, '11'),
(36, 4, 'Análisis Social', 'Problemáticas sociales contemporáneas', NULL, '11'),
(37, 5, 'Vocabulary', 'Vocabulario básico', NULL, '9'),
(38, 5, 'Grammar Basics', 'Estructuras gramaticales básicas', NULL, '9'),
(39, 5, 'Reading Basics', 'Comprensión de textos sencillos', NULL, '9'),
(40, 5, 'Intermediate Grammar', 'Tiempos verbales y estructuras', NULL, '10'),
(41, 5, 'Reading Comprehension', 'Comprensión de textos intermedios', NULL, '10'),
(42, 5, 'Listening Strategies', 'Estrategias de comprensión auditiva', NULL, '10'),
(43, 5, 'Advanced Reading', 'Comprensión avanzada de textos', NULL, '11'),
(44, 5, 'ICFES English Skills', 'Competencias evaluadas en Saber 11', NULL, '11'),
(45, 5, 'Contextual Vocabulary', 'Vocabulario en contexto', NULL, '11'),
(47, 5, 'Verbo To-Be', 'El estudiante deberá de aprender acerca del verbo to-be y del como emplearlo en inglés.', '<p>Verbo to-be&nbsp;<br>ñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñiñ</p><div class=\"info-box\"><div class=\"bloque-label\">💡 CONCEPTO CLAVE</div><p>ñañañañañaña</p></div><p>ñiñiñiñiñiñiñiññiñiñiññiiññiññi</p>', '9');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombres` varchar(100) NOT NULL,
  `apellidos` varchar(100) NOT NULL,
  `correo` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `grado` enum('9','10','11') NOT NULL,
  `id_curso` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'avatar_default.png',
  `id_avatar` int(11) DEFAULT 1,
  `puntos` int(11) DEFAULT 0,
  `nivel` int(11) DEFAULT 1,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `numero_documento` varchar(20) NOT NULL,
  `id_rol` int(11) NOT NULL DEFAULT 2,
  `id_institucion` int(11) DEFAULT 1,
  `primer_ingreso` tinyint(1) DEFAULT 1,
  `estado` enum('Activo','Inactivo') DEFAULT 'Activo',
  `ultimo_acceso` datetime DEFAULT NULL,
  `fecha_cambio_password` datetime DEFAULT NULL,
  `ultimo_intento` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombres`, `apellidos`, `correo`, `password`, `grado`, `id_curso`, `avatar`, `id_avatar`, `puntos`, `nivel`, `fecha_registro`, `numero_documento`, `id_rol`, `id_institucion`, `primer_ingreso`, `estado`, `ultimo_acceso`, `fecha_cambio_password`, `ultimo_intento`) VALUES
(1, 'Carlos', 'Perez', 'carlos.prueba@icfes.local', '$2y$10$TyBiQIb1Xe9xJk1dhf/NW.TOqaGl1.o09N8bHycS4xotoRQ3kso8m', '11', 12, 'avatar_default.png', 1, 0, 1, '2026-08-13 03:56:00', '1234567890', 2, 1, 1, 'Activo', '2026-08-13 00:12:13', NULL, NULL),
(2, 'Administrador', 'Principal', 'admin@icfes.local', '$2y$10$WsIg1GC05G6XeRDJGfKFYOqKVZZfeb8FEH6.2LoYGscAZuDyci2Va', '11', NULL, 'avatar_default.png', 1, 0, 1, '2026-08-13 04:46:03', 'ADMIN001', 1, 1, 0, 'Activo', '2026-08-30 09:52:05', NULL, NULL),
(3, 'Juan', 'Pérez Gómez', 'juan.prueba@test.com', '$2y$10$ryo/zL60XLnp4Sro9ZbWOeLmMi5pqFWnxtmEbmMBVRbwmE7vJHsmu', '11', 12, 'avatar_default.png', 1, 0, 1, '2026-08-16 04:17:23', '100000001', 2, 1, 1, 'Activo', '2026-08-15 23:19:26', NULL, NULL),
(4, 'María', 'Rodríguez López', 'maria.prueba@test.com', '$2y$10$b8CKTiFjMdXEcExgv8U9c.M2lDo5XCd96sYadC05BLbfarzHCfVB.', '10', 11, 'avatar_default.png', 1, 0, 1, '2026-08-16 04:17:23', '100000002', 2, 1, 0, 'Activo', '2026-08-30 09:49:13', '2026-08-30 09:49:21', NULL),
(5, 'Carlos', 'Martínez Díaz', 'carlos.prueba@test.com', '$2y$10$SfR4wmeBlRzl3nu3sSDiBuOZQJ94Rgc4Ivf1ZrpFrhBHyAX.tOjlm', '9', 10, 'avatar_default.png', 1, 0, 1, '2026-08-16 04:17:24', '100000003', 2, 1, 1, 'Activo', NULL, NULL, NULL),
(7, 'Pepe', 'Pepencio', 'popis@gmail.com', '$2y$10$NWlpgDVF84RUIYiN9b5UNeHQeoor99nhpC1mzZXs4z8POosz8J3se', '9', 15, 'avatar1.png', 1, 0, 1, '2026-08-16 06:21:14', '12345', 2, 1, 0, 'Activo', '2026-08-30 11:47:12', '2026-08-26 22:31:44', NULL),
(8, 'Joel', 'Rivera', 'juan@correo.com', '$2y$10$bRyqLOPwxxDXmWMNI.VmauQf5py5FKDD2OAH5lrMTsroqBk30r4.O', '11', 13, 'avatar_default.png', 1, 60, 1, '2026-08-28 07:10:19', '1073533196', 2, 1, 0, 'Activo', '2026-08-30 09:54:32', '2026-08-28 02:12:53', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios_insignias`
--

CREATE TABLE `usuarios_insignias` (
  `id_usuario` int(11) NOT NULL,
  `id_insignia` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `avatares`
--
ALTER TABLE `avatares`
  ADD PRIMARY KEY (`id_avatar`);

--
-- Indices de la tabla `competencias`
--
ALTER TABLE `competencias`
  ADD PRIMARY KEY (`id_competencia`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indices de la tabla `componentes`
--
ALTER TABLE `componentes`
  ADD PRIMARY KEY (`id_componente`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id_configuracion`);

--
-- Indices de la tabla `contenido_temas`
--
ALTER TABLE `contenido_temas`
  ADD PRIMARY KEY (`id_contenido`),
  ADD KEY `idx_contenido_tema` (`id_tema`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id_curso`);

--
-- Indices de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD PRIMARY KEY (`id_evaluacion`),
  ADD KEY `id_materia` (`id_materia`),
  ADD KEY `id_tema` (`id_tema`);

--
-- Indices de la tabla `historial_puntos`
--
ALTER TABLE `historial_puntos`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `idx_historial_usuario_referencia` (`id_usuario`,`referencia`);

--
-- Indices de la tabla `insignias`
--
ALTER TABLE `insignias`
  ADD PRIMARY KEY (`id_insignia`);

--
-- Indices de la tabla `instituciones`
--
ALTER TABLE `instituciones`
  ADD PRIMARY KEY (`id_institucion`);

--
-- Indices de la tabla `intentos`
--
ALTER TABLE `intentos`
  ADD PRIMARY KEY (`id_intento`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_evaluacion` (`id_evaluacion`);

--
-- Indices de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `materias`
--
ALTER TABLE `materias`
  ADD PRIMARY KEY (`id_materia`);

--
-- Indices de la tabla `niveles`
--
ALTER TABLE `niveles`
  ADD PRIMARY KEY (`id_nivel`);

--
-- Indices de la tabla `opciones`
--
ALTER TABLE `opciones`
  ADD PRIMARY KEY (`id_opcion`),
  ADD UNIQUE KEY `uq_opciones_pregunta_opcion` (`id_pregunta`,`opcion`);

--
-- Indices de la tabla `preguntas`
--
ALTER TABLE `preguntas`
  ADD PRIMARY KEY (`id_pregunta`),
  ADD KEY `id_evaluacion` (`id_evaluacion`),
  ADD KEY `id_tema` (`id_tema`),
  ADD KEY `fk_preguntas_competencia` (`id_competencia`),
  ADD KEY `fk_preguntas_componente` (`id_componente`);

--
-- Indices de la tabla `progreso`
--
ALTER TABLE `progreso`
  ADD PRIMARY KEY (`id_progreso`),
  ADD UNIQUE KEY `uq_progreso_usuario_tema` (`id_usuario`,`id_tema`),
  ADD KEY `id_tema` (`id_tema`),
  ADD KEY `idx_progreso_usuario_completado` (`id_usuario`,`completado`);

--
-- Indices de la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD PRIMARY KEY (`id_recurso`),
  ADD KEY `id_tema` (`id_tema`);

--
-- Indices de la tabla `respuestas_usuario`
--
ALTER TABLE `respuestas_usuario`
  ADD PRIMARY KEY (`id_respuesta`),
  ADD UNIQUE KEY `uq_respuesta_intento_pregunta` (`id_intento`,`id_pregunta`),
  ADD KEY `id_pregunta` (`id_pregunta`),
  ADD KEY `id_opcion` (`id_opcion`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `sugerencias`
--
ALTER TABLE `sugerencias`
  ADD PRIMARY KEY (`id_sugerencia`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `temas`
--
ALTER TABLE `temas`
  ADD PRIMARY KEY (`id_tema`),
  ADD KEY `id_materia` (`id_materia`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD UNIQUE KEY `numero_documento` (`numero_documento`),
  ADD KEY `fk_usuario_rol` (`id_rol`),
  ADD KEY `fk_usuario_institucion` (`id_institucion`),
  ADD KEY `fk_usuario_curso` (`id_curso`),
  ADD KEY `fk_usuario_avatar` (`id_avatar`);

--
-- Indices de la tabla `usuarios_insignias`
--
ALTER TABLE `usuarios_insignias`
  ADD PRIMARY KEY (`id_usuario`,`id_insignia`),
  ADD KEY `id_insignia` (`id_insignia`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `avatares`
--
ALTER TABLE `avatares`
  MODIFY `id_avatar` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `competencias`
--
ALTER TABLE `competencias`
  MODIFY `id_competencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `componentes`
--
ALTER TABLE `componentes`
  MODIFY `id_componente` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id_configuracion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `contenido_temas`
--
ALTER TABLE `contenido_temas`
  MODIFY `id_contenido` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id_curso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT de la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  MODIFY `id_evaluacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `historial_puntos`
--
ALTER TABLE `historial_puntos`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `insignias`
--
ALTER TABLE `insignias`
  MODIFY `id_insignia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `instituciones`
--
ALTER TABLE `instituciones`
  MODIFY `id_institucion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `intentos`
--
ALTER TABLE `intentos`
  MODIFY `id_intento` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `materias`
--
ALTER TABLE `materias`
  MODIFY `id_materia` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `niveles`
--
ALTER TABLE `niveles`
  MODIFY `id_nivel` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `opciones`
--
ALTER TABLE `opciones`
  MODIFY `id_opcion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `preguntas`
--
ALTER TABLE `preguntas`
  MODIFY `id_pregunta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `progreso`
--
ALTER TABLE `progreso`
  MODIFY `id_progreso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `recursos`
--
ALTER TABLE `recursos`
  MODIFY `id_recurso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `respuestas_usuario`
--
ALTER TABLE `respuestas_usuario`
  MODIFY `id_respuesta` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `sugerencias`
--
ALTER TABLE `sugerencias`
  MODIFY `id_sugerencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `temas`
--
ALTER TABLE `temas`
  MODIFY `id_tema` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `competencias`
--
ALTER TABLE `competencias`
  ADD CONSTRAINT `competencias_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `componentes`
--
ALTER TABLE `componentes`
  ADD CONSTRAINT `componentes_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `contenido_temas`
--
ALTER TABLE `contenido_temas`
  ADD CONSTRAINT `fk_contenido_tema` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `evaluaciones`
--
ALTER TABLE `evaluaciones`
  ADD CONSTRAINT `evaluaciones_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`),
  ADD CONSTRAINT `evaluaciones_ibfk_2` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`);

--
-- Filtros para la tabla `historial_puntos`
--
ALTER TABLE `historial_puntos`
  ADD CONSTRAINT `historial_puntos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `intentos`
--
ALTER TABLE `intentos`
  ADD CONSTRAINT `intentos_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `intentos_ibfk_2` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones` (`id_evaluacion`);

--
-- Filtros para la tabla `logs_sistema`
--
ALTER TABLE `logs_sistema`
  ADD CONSTRAINT `logs_sistema_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `opciones`
--
ALTER TABLE `opciones`
  ADD CONSTRAINT `opciones_ibfk_1` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas` (`id_pregunta`) ON DELETE CASCADE;

--
-- Filtros para la tabla `preguntas`
--
ALTER TABLE `preguntas`
  ADD CONSTRAINT `fk_preguntas_competencia` FOREIGN KEY (`id_competencia`) REFERENCES `competencias` (`id_competencia`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_preguntas_componente` FOREIGN KEY (`id_componente`) REFERENCES `componentes` (`id_componente`) ON UPDATE CASCADE,
  ADD CONSTRAINT `preguntas_ibfk_1` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones` (`id_evaluacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `preguntas_ibfk_2` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`);

--
-- Filtros para la tabla `progreso`
--
ALTER TABLE `progreso`
  ADD CONSTRAINT `progreso_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `progreso_ibfk_2` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`) ON DELETE CASCADE;

--
-- Filtros para la tabla `recursos`
--
ALTER TABLE `recursos`
  ADD CONSTRAINT `recursos_ibfk_1` FOREIGN KEY (`id_tema`) REFERENCES `temas` (`id_tema`) ON DELETE CASCADE;

--
-- Filtros para la tabla `respuestas_usuario`
--
ALTER TABLE `respuestas_usuario`
  ADD CONSTRAINT `respuestas_usuario_ibfk_1` FOREIGN KEY (`id_intento`) REFERENCES `intentos` (`id_intento`) ON DELETE CASCADE,
  ADD CONSTRAINT `respuestas_usuario_ibfk_2` FOREIGN KEY (`id_pregunta`) REFERENCES `preguntas` (`id_pregunta`),
  ADD CONSTRAINT `respuestas_usuario_ibfk_3` FOREIGN KEY (`id_opcion`) REFERENCES `opciones` (`id_opcion`);

--
-- Filtros para la tabla `sugerencias`
--
ALTER TABLE `sugerencias`
  ADD CONSTRAINT `sugerencias_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `temas`
--
ALTER TABLE `temas`
  ADD CONSTRAINT `temas_ibfk_1` FOREIGN KEY (`id_materia`) REFERENCES `materias` (`id_materia`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_avatar` FOREIGN KEY (`id_avatar`) REFERENCES `avatares` (`id_avatar`),
  ADD CONSTRAINT `fk_usuario_curso` FOREIGN KEY (`id_curso`) REFERENCES `cursos` (`id_curso`),
  ADD CONSTRAINT `fk_usuario_institucion` FOREIGN KEY (`id_institucion`) REFERENCES `instituciones` (`id_institucion`),
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`);

--
-- Filtros para la tabla `usuarios_insignias`
--
ALTER TABLE `usuarios_insignias`
  ADD CONSTRAINT `usuarios_insignias_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `usuarios_insignias_ibfk_2` FOREIGN KEY (`id_insignia`) REFERENCES `insignias` (`id_insignia`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
