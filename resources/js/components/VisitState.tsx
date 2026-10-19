import { router } from '@inertiajs/react';
import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';

type VisitState = { loading: boolean; failed: boolean };

const VisitContext = createContext<VisitState>({ loading: false, failed: false });

function keepsPageState(preserveState: unknown): boolean {
    return preserveState === true || preserveState === 'errors';
}

export function VisitProvider({ children }: { children: ReactNode }) {
    const [state, setState] = useState<VisitState>({ loading: false, failed: false });

    useEffect(() => {
        const stopStart = router.on('start', (event) => {
            if (!keepsPageState(event.detail.visit.preserveState)) {
                return;
            }

            setState({ loading: true, failed: false });
        });
        const stopFinish = router.on('finish', () => {
            setState((current) => ({ ...current, loading: false }));
        });
        const stopCancel = router.on('cancel', () => {
            setState((current) => ({ ...current, loading: false }));
        });
        const stopHttp = router.on('httpException', () => {
            setState({ loading: false, failed: true });
        });
        const stopNetwork = router.on('networkError', () => {
            setState({ loading: false, failed: true });
        });

        return () => {
            stopStart();
            stopFinish();
            stopCancel();
            stopHttp();
            stopNetwork();
        };
    }, []);

    return <VisitContext.Provider value={state}>{children}</VisitContext.Provider>;
}

export function useVisitState(): VisitState {
    return useContext(VisitContext);
}
