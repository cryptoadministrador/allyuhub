import { render, screen } from '@testing-library/react';
import { axe } from 'vitest-axe';
import { describe, expect, it } from 'vitest';
import Anillo from '../Anillo';
import { violacionesGraves } from '../../test/helpers';

describe('Anillo de progreso', () => {
    it('anuncia el valor y lo escribe como texto (nunca solo el dibujo)', () => {
        render(<Anillo valor={0.42} etiqueta="Dominio de Física" />);

        const barra = screen.getByRole('progressbar', { name: 'Dominio de Física' });
        expect(barra).toHaveAttribute('aria-valuenow', '42');
        expect(barra).toHaveAttribute('aria-valuemin', '0');
        expect(barra).toHaveAttribute('aria-valuemax', '100');
        expect(barra).toHaveAttribute('aria-valuetext', '42 por ciento');
        expect(screen.getByText('42%')).toBeInTheDocument();
    });

    it('recorta valores imposibles en vez de dibujar fuera del círculo', () => {
        const { rerender, container } = render(<Anillo valor={5} etiqueta="x" />);
        expect(screen.getByText('100%')).toBeInTheDocument();
        // Arco completo: el hueco del trazo es cero.
        expect(container.querySelectorAll('circle')[1].getAttribute('stroke-dashoffset')).toBe('0');

        rerender(<Anillo valor={-3} etiqueta="x" />);
        expect(screen.getByText('0%')).toBeInTheDocument();

        rerender(<Anillo valor={undefined} etiqueta="x" />);
        expect(screen.getByText('0%')).toBeInTheDocument();

        rerender(<Anillo valor={NaN} etiqueta="x" />);
        expect(screen.getByText('0%')).toBeInTheDocument();
    });

    it('dibuja el arco proporcional al valor', () => {
        const { container } = render(<Anillo valor={0.25} etiqueta="x" tamano={100} grosor={10} />);
        const [fondo, arco] = container.querySelectorAll('circle');

        const circunferencia = 2 * Math.PI * 45;
        expect(Number(fondo.getAttribute('r'))).toBe(45);
        expect(Number(arco.getAttribute('stroke-dasharray'))).toBeCloseTo(circunferencia, 5);
        expect(Number(arco.getAttribute('stroke-dashoffset'))).toBeCloseTo(circunferencia * 0.75, 5);
    });

    it('cuando es decorativo no se anuncia dos veces', () => {
        const { container } = render(<Anillo valor={1} etiqueta="x" decorativo />);

        expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
        expect(container.querySelector('svg').getAttribute('aria-hidden')).toBe('true');
    });

    it('no tiene violaciones graves de accesibilidad', async () => {
        const { container } = render(<Anillo valor={0.6} etiqueta="Dominio" />);

        expect(violacionesGraves(await axe(container))).toEqual([]);
    });
});
