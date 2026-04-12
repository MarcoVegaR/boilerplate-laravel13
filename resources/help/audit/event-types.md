---
title: Tipos de eventos y fuentes de auditoría
summary: Referencia de los tipos de eventos que registra el sistema y cómo diferenciar eventos de modelos de eventos de seguridad.
category: Auditoría
order: 20
---

La auditoría del sistema agrupa dos tipos de eventos en una vista unificada: cambios en datos de modelos y eventos de seguridad.

## Fuentes disponibles

| Fuente | Qué registra |
| --- | --- |
| **Modelos** | Creaciones, actualizaciones y eliminaciones de registros |
| **Seguridad** | Intentos de sesión, cierres, cambios de contraseña y acciones de 2FA |

## Eventos de modelos más comunes

| Evento | Cuándo ocurre |
| --- | --- |
| `created` | Se creó un registro nuevo |
| `updated` | Se modificaron campos de un registro |
| `deleted` | Se eliminó un registro |

## Eventos de seguridad más comunes

| Evento | Cuándo ocurre |
| --- | --- |
| Inicio de sesión | El usuario autenticó correctamente |
| Intento fallido | Credenciales incorrectas o cuenta inactiva |
| Cierre de sesión | El usuario cerró la sesión manualmente |
| 2FA configurado | Se activó el segundo factor |
| Contraseña cambiada | Se actualizó la contraseña de la cuenta |

## Cómo usar las fuentes para investigar

- Usa **Modelos** cuando necesitas saber quién cambió un dato y qué valores tenía antes.
- Usa **Seguridad** cuando necesitas rastrear intentos de acceso, horarios de sesión o cambios de credenciales.
- Usa **Todas** para ver el contexto completo de una situación que involucra datos y acceso al mismo tiempo.

## Campos del detalle

Al abrir un evento, el detalle muestra:

- **Actor**: quién ejecutó la acción.
- **Entidad**: sobre qué registro se actuó.
- **Valores anteriores y nuevos**: para eventos de modelo.
- **Metadatos adicionales**: para eventos de seguridad (IP, agente, etc.).
