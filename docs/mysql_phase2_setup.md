# Fase 2 · Alta de base MySQL del CRM

## Base de datos a usar
Usa la base **existente** del proyecto:

- `telefonosbd`

Es la misma que ya usa `Comercial` y la conexion central nueva del CRM apunta ahi por defecto.

## Fichero SQL a importar
Importa este fichero:

- [sql/mysql_phase1_schema.sql](/var/www/html/atupuerta/sql/mysql_phase1_schema.sql)

Ese SQL ya incluye:

- `CREATE DATABASE IF NOT EXISTS telefonosbd`
- `USE telefonosbd`
- creacion de todas las tablas `crm_*`
- indices base de la migracion

## Opcion 1 · Importar desde consola

### 1. Entrar al servidor
Abre terminal en el servidor donde corre el CRM.

### 2. Ejecutar el import
Lanza:

```bash
mysql -u telefonosuser -p < /var/www/html/atupuerta/sql/mysql_phase1_schema.sql
```

Te pedira la password del usuario MySQL.

### 3. Verificar que se han creado las tablas
Entra a MySQL:

```bash
mysql -u telefonosuser -p
```

Y ejecuta:

```sql
USE telefonosbd;
SHOW TABLES LIKE 'crm_%';
```

Deberias ver las tablas `crm_*` nuevas.

## Opcion 2 · Importar desde phpMyAdmin

### 1. Abrir phpMyAdmin
Entra con el usuario MySQL que ya usa el proyecto.

### 2. Seleccionar la base
Selecciona:

- `telefonosbd`

### 3. Ir a Importar
En la pestana `Importar`, sube el fichero:

- `/var/www/html/atupuerta/sql/mysql_phase1_schema.sql`

### 4. Ejecutar la importacion
Pulsa `Continuar`.

### 5. Verificar
Comprueba que aparecen las tablas `crm_*`.

## Comprobacion recomendada
Despues del import, ejecuta:

```sql
USE telefonosbd;
SELECT COUNT(*) AS total
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'telefonosbd'
  AND TABLE_NAME LIKE 'crm\_%';
```

## Importante en esta fase

- En Fase 2 **no** hay que importar datos todavia.
- Solo hay que crear la estructura y dejar lista la capa de persistencia.
- El backend operativo puede seguir en `json` mientras no empiece la Fase 3.

## Backend configurable
El proyecto ya reconoce:

- `json`
- `dual`
- `mysql`

Se puede forzar temporalmente por entorno con:

```bash
export CRM_STORAGE_BACKEND=json
```

o:

```bash
export CRM_STORAGE_BACKEND=dual
```

o:

```bash
export CRM_STORAGE_BACKEND=mysql
```

Si no se define variable de entorno, el sistema cae por defecto en `json`.
