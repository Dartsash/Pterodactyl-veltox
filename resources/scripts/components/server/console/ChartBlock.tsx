import React from 'react';
import classNames from 'classnames';
import styles from '@/components/server/console/style.module.css';

interface ChartBlockProps {
    title: string;
    /** Current reading, shown in the header so the graph is readable at a glance. */
    value?: React.ReactNode;
    legend?: React.ReactNode;
    children: React.ReactNode;
}

export default ({ title, value, legend, children }: ChartBlockProps) => (
    <div className={classNames(styles.chart_container, 'group')}>
        <div className={'flex items-center gap-3 px-4 py-2'}>
            <h3 className={'font-header text-2xs font-bold uppercase tracking-wider text-gray-400'}>{title}</h3>
            {legend && <div className={'ml-auto flex items-center text-xs text-gray-400'}>{legend}</div>}
            {!legend && value !== undefined && (
                <span className={'ml-auto text-sm font-bold text-gray-50 tabular-nums'}>{value}</span>
            )}
        </div>
        <div className={'z-10 ml-2'}>{children}</div>
    </div>
);
