---
title: Asignar roles a un usuario
summary: Guía para vincular roles activos a una cuenta y validar los permisos efectivos resultantes.
category: Usuarios
order: 40
---

Los roles determinan qué puede hacer una persona en el sistema. Asignarlos correctamente es la forma más directa de controlar el acceso.

## Antes de asignar

- Revisa en el listado de roles cuáles están activos y cuáles cubren el caso.
- Confirma que el rol tiene los permisos mínimos necesarios para la tarea esperada.
- Evita asignar roles genéricos amplios cuando existe uno más específico.

## Pasos

1. Abre la cuenta de usuario desde el listado o la búsqueda.
2. Entra a la opción de edición.
3. En el selector de roles, marca los roles que correspondan.
4. Guarda los cambios.
5. Verifica en **Configuración > Acceso** que los permisos efectivos son los esperados.

## Cómo validar el resultado

| Señal | Qué indica |
| --- | --- |
| El rol aparece en el perfil | La asignación se guardó correctamente |
| Los permisos efectivos incluyen lo esperado | El rol tiene los permisos necesarios |
| No aparecen permisos adicionales inesperados | No se asignó más acceso del necesario |

## Buenas prácticas

- Asigna el mínimo de roles suficientes para el trabajo.
- Si necesitas agregar un permiso puntual, evalúa si corresponde crear un rol específico o ajustar uno existente.
- Documenta en tu equipo cuáles roles están pensados para qué función.
