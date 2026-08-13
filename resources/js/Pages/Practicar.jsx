import { Head } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';

/** Esqueleto de la fase B; el bucle de práctica completo llega en la fase C. */
export default function Practicar({ objective }) {
    return (
        <AppLayout title={`Practicar ${objective.native_code}`}>
            <Head title={`Practicar ${objective.native_code}`} />
            <p>{objective.statement}</p>
        </AppLayout>
    );
}
