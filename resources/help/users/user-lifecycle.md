---
title: Estados y ciclo de vida de un usuario
summary: Referencia de los estados posibles de una cuenta y qué operaciones están disponibles en cada uno.
category: Usuarios
order: 50
---

Cada cuenta pasa por distintos estados según su configuración de seguridad y actividad operativa.

## Estados posibles

| Estado | Qué significa | Puede iniciar sesión |
| --- | --- | --- |
| **Activo** | Cuenta habilitada y configurada | Sí, según requisitos de seguridad |
| **Inactivo** | Cuenta suspendida manualmente | No |
| **Sin verificar** | Correo pendiente de confirmación | No |
| **Sin segundo factor** | Entorno con 2FA obligatorio y factor no configurado | No |

## Transiciones típicas

```text
Creación → Sin verificar → Activo → Inactivo → Activo
```

- Una cuenta recién creada puede no tener el correo verificado si el entorno lo exige.
- Un usuario activo puede desactivarse en cualquier momento.
- Un usuario inactivo puede reactivarse sin perder configuración ni auditoría.

## Operaciones por estado

| Operación | Activo | Inactivo |
| --- | --- | --- |
| Editar datos | ✅ | ✅ |
| Asignar roles | ✅ | ✅ |
| Desactivar | ✅ | — |
| Reactivar | — | ✅ |
| Reiniciar contraseña | ✅ | ✅ |
| Ver auditoría | ✅ | ✅ |

## Punto de control antes de actuar

Antes de cambiar el estado de una cuenta, revisa en la auditoría si tiene actividad reciente para entender el impacto del cambio.
