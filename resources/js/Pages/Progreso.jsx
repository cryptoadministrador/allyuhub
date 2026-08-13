import { Head } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

/** Esqueleto de la fase B; el tablero de progreso completo llega en la fase D. */
export default function Progreso({ tracks }) {
    return (
        <AppLayout title="Mi progreso">
            <Head title="Mi progreso" />
            <p>Trayectos disponibles: {tracks.map((t) => t.label).join(', ')}</p>
        </AppLayout>
    );
}
