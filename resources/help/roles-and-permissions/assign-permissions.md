---
title: Asignar permisos a un rol
summary: Cómo seleccionar los permisos correctos al crear o editar un rol sin exceder el acceso necesario.
category: Roles y permisos
order: 30
---

Los permisos definen qué acciones puede ejecutar un rol dentro de cada módulo. Seleccionarlos con precisión reduce el riesgo de acceso no intencional.

## Cómo están organizados los permisos

Los permisos se agrupan por módulo y tipo de operación:

- `sistema.usuarios.ver` — permite listar y consultar usuarios
- `sistema.usuarios.crear` — permite crear cuentas nuevas
- `sistema.usuarios.editar` — permite modificar datos de cuentas existentes
- `sistema.usuarios.eliminar` — permite borrar cuentas

El patrón se repite para cada módulo del sistema.

## Pasos para asignar permisos

1. Abre el rol desde el listado o crea uno nuevo.
2. Usa el selector de permisos agrupado por módulo.
3. Marca solo los permisos necesarios para el caso.
4. Guarda el rol.
5. Asigna el rol a un usuario de prueba y verifica en **Configuración > Acceso** que los permisos efectivos coinciden.

## Regla práctica

Si dudas entre incluir o no un permiso, **no lo incluyas**. Es más fácil agregar un permiso después que auditar un acceso indebido.

## Qué revisar después

- El usuario con el rol puede hacer lo esperado.
- El usuario no puede hacer más de lo esperado.
- El cambio queda registrado en la auditoría del sistema.
