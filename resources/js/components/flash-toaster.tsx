import { usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { toast } from 'sonner';
import type { Flash } from '@/types/ui';

/**
 * FlashToaster — renderless component that bridges Laravel flash session data
 * with the Sonner toast system.
 *
 * Mount once in the authenticated layout alongside <Toaster />. It reads
 * `flash` from Inertia shared props and fires the appropriate toast when the
 * flash value changes (i.e. after a redirect with ->with('success', '…')).
 *
 * De-duplication: a ref tracks the previous flash snapshot so back-navigation
 * or re-renders that do not change the flash values do not re-fire toasts.
 *
 * NOTE: This is a UI convenience only. Backend enforcement is always required.
 */
export function FlashToaster() {
    const { flash } = usePage<{ flash: Flash }>().props;

    const prevRef = useRef<Flash>({
        success: null,
        error: null,
        info: null,
        warning: null,
    });

    useEffect(() => {
        const prev = prevRef.current;
        let fired = false;

        if (flash?.success && flash.success !== prev.success) {
            toast.success(flash.success);
            fired = true;
        }

        if (flash?.error && flash.error !== prev.error) {
            toast.error(flash.error);
            fired = true;
        }

        if (flash?.info && flash.info !== prev.info) {
            toast.info(flash.info);
            fired = true;
        }

        if (flash?.warning && flash.warning !== prev.warning) {
            toast.warning(flash.warning);
            fired = true;
        }

        // After firing, reset to null so repeated identical messages on
        // subsequent visits will still trigger. The useEffect dependency
        // array prevents double-firing within the same render cycle.
        prevRef.current = fired
            ? { success: null, error: null, info: null, warning: null }
            : {
                  success: flash?.success ?? null,
                  error: flash?.error ?? null,
                  info: flash?.info ?? null,
                  warning: flash?.warning ?? null,
              };
    }, [flash?.success, flash?.error, flash?.info, flash?.warning]);

    return null;
}
