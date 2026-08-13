import { Head } from '@inertiajs/react';
import AppLayout from '../layouts/AppLayout';

/** Un simulador publicado: título y acceso al bundle (CDN). */
export default function Recurso({ resource }) {
    return (
        <AppLayout title={resource.title}>
            <Head title={resource.title} />
            {resource.bundle_url ? (
                <p>
                    <a
                        href={resource.bundle_url}
                        className="inline-block rounded bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-700 focus:outline-2 focus:outline-offset-2 focus:outline-indigo-600"
                    >
                        Abrir el laboratorio
                    </a>
                </p>
            ) : (
                <p role="status">Este recurso aún no tiene una versión publicada con bundle.</p>
            )}
        </AppLayout>
    );
}
