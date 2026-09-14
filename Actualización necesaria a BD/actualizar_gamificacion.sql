-- Studia360 - Actualización de gamificación
-- MariaDB 10.4+
-- Ejecutar una sola vez sobre la base de datos actual.
-- Hace que cada nivel pueda tener un avatar y cada materia una insignia.

SET FOREIGN_KEY_CHECKS=0;

-- ============================================================
-- 1. AVATARES: asociar exactamente un avatar a cada nivel
-- ============================================================

ALTER TABLE avatares
  ADD COLUMN id_nivel INT(11) NULL AFTER id_avatar;

-- Relación inicial según la configuración actual:
-- Avatar 1 -> Nivel 1, ..., Avatar 5 -> Nivel 5.
UPDATE avatares
SET id_nivel = id_avatar
WHERE id_avatar BETWEEN 1 AND 5;

-- Si existen avatares adicionales, quedan sin nivel para que el
-- administrador los pueda asignar desde el panel.

ALTER TABLE avatares
  ADD KEY idx_avatares_nivel (id_nivel);

ALTER TABLE avatares
  ADD CONSTRAINT fk_avatares_nivel
  FOREIGN KEY (id_nivel) REFERENCES niveles(id_nivel)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

-- Impide que dos avatares activos/configurados utilicen el mismo nivel.
ALTER TABLE avatares
  ADD UNIQUE KEY uq_avatar_nivel (id_nivel);

-- Mantener puntos_requeridos sincronizados con el mínimo del nivel.
UPDATE avatares a
INNER JOIN niveles n ON n.id_nivel = a.id_nivel
SET a.puntos_requeridos = n.puntos_minimos;

-- ============================================================
-- 2. INSIGNIAS: asociar una insignia a cada materia
-- ============================================================

ALTER TABLE insignias
  ADD COLUMN id_materia INT(11) NULL AFTER id_insignia;

ALTER TABLE insignias
  ADD KEY idx_insignias_materia (id_materia);

ALTER TABLE insignias
  ADD CONSTRAINT fk_insignias_materia
  FOREIGN KEY (id_materia) REFERENCES materias(id_materia)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

-- Una materia tendrá como máximo una insignia.
ALTER TABLE insignias
  ADD UNIQUE KEY uq_insignia_materia (id_materia);

-- ============================================================
-- 3. CREAR LAS INSIGNIAS BASE DE LAS CINCO MATERIAS
-- ============================================================

INSERT INTO insignias
  (id_materia, nombre, descripcion, imagen, criterio, puntos_otorgados, estado)
SELECT 1, 'Maestro de Matemáticas',
       'Completaste todos los temas de Matemáticas correspondientes a tu grado.',
       NULL, 'materia_completa', 0, 'Activa'
WHERE NOT EXISTS (SELECT 1 FROM insignias WHERE id_materia = 1);

INSERT INTO insignias
  (id_materia, nombre, descripcion, imagen, criterio, puntos_otorgados, estado)
SELECT 2, 'Maestro de Lectura',
       'Completaste todos los temas de Lectura Crítica correspondientes a tu grado.',
       NULL, 'materia_completa', 0, 'Activa'
WHERE NOT EXISTS (SELECT 1 FROM insignias WHERE id_materia = 2);

INSERT INTO insignias
  (id_materia, nombre, descripcion, imagen, criterio, puntos_otorgados, estado)
SELECT 3, 'Maestro de Ciencias',
       'Completaste todos los temas de Ciencias Naturales correspondientes a tu grado.',
       NULL, 'materia_completa', 0, 'Activa'
WHERE NOT EXISTS (SELECT 1 FROM insignias WHERE id_materia = 3);

INSERT INTO insignias
  (id_materia, nombre, descripcion, imagen, criterio, puntos_otorgados, estado)
SELECT 4, 'Maestro de Sociales',
       'Completaste todos los temas de Sociales y Ciudadanas correspondientes a tu grado.',
       NULL, 'materia_completa', 0, 'Activa'
WHERE NOT EXISTS (SELECT 1 FROM insignias WHERE id_materia = 4);

INSERT INTO insignias
  (id_materia, nombre, descripcion, imagen, criterio, puntos_otorgados, estado)
SELECT 5, 'Maestro de Inglés',
       'Completaste todos los temas de Inglés correspondientes a tu grado.',
       NULL, 'materia_completa', 0, 'Activa'
WHERE NOT EXISTS (SELECT 1 FROM insignias WHERE id_materia = 5);

-- Todas las insignias de este modelo usan el criterio automático.
UPDATE insignias
SET criterio = 'materia_completa'
WHERE id_materia IS NOT NULL;

SET FOREIGN_KEY_CHECKS=1;

-- Verificación rápida:
-- SELECT a.id_avatar,a.nombre,a.id_nivel,n.nombre AS nivel
-- FROM avatares a LEFT JOIN niveles n ON n.id_nivel=a.id_nivel
-- ORDER BY a.id_avatar;
--
-- SELECT i.id_insignia,i.nombre,i.id_materia,m.nombre AS materia,i.criterio
-- FROM insignias i LEFT JOIN materias m ON m.id_materia=i.id_materia
-- ORDER BY i.id_insignia;
