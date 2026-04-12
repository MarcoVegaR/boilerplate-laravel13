---
title: Revisar mi acceso actual
summary: Guía para interpretar tus roles y permisos efectivos antes de solicitar cambios o reportar bloqueos.
category: Seguridad y acceso
order: 10
---

Usa esta vista para entender qué permisos realmente están habilitados en tu cuenta y desde qué roles provienen.

## Lectura rápida

| Elemento           | Qué significa                             | Qué revisar                          |
| ------------------ | ----------------------------------------- | ------------------------------------ |
| Mis roles          | Roles asignados directamente a tu usuario | Si están activos y son los esperados |
| Permisos efectivos | Resultado de combinar roles activos       | Si el permiso clave está presente    |

## Checklist

1. Abre **Configuración > Acceso**.
2. Revisa si el rol esperado aparece en estado activo.
3. Busca el grupo de permisos relacionado con tu tarea.
4. Identifica desde qué rol llega cada permiso efectivo.

## Ejemplo útil

```text
Si no puedes abrir un módulo, primero valida si el permiso de vista existe en tus permisos efectivos.
```

## Cuando escalar

- Si el rol correcto no aparece asignado.
- Si el rol existe, pero está inactivo.
- Si el permiso falta incluso con el rol esperado activo.
