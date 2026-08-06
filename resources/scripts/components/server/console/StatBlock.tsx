import React from 'react';
import Icon from '@/components/elements/Icon';
import { IconDefinition } from '@fortawesome/free-solid-svg-icons';
import classNames from 'classnames';
import CopyOnClick from '@/components/elements/CopyOnClick';

export type StatTone = 'warning' | 'danger' | undefined;

interface StatBlockProps {
    title: string;
    copyOnClick?: string;
    tone?: StatTone;
    icon: IconDefinition;
    children: React.ReactNode;
    className?: string;
}

const iconTone: Record<'warning' | 'danger' | 'default', string> = {
    default: 'bg-raised text-gray-300',
    warning: 'bg-warning-500/15 text-warning-400',
    danger: 'bg-danger-500/15 text-danger-400',
};

const valueTone: Record<'warning' | 'danger' | 'default', string> = {
    default: 'text-gray-50',
    warning: 'text-warning-400',
    danger: 'text-danger-400',
};

/**
 * A single metric row in the console side rail.
 *
 * This deliberately no longer uses `use-fit-text`. Auto-fitting gave every row a
 * slightly different font size, so the rail never lined up vertically. Values now
 * use one fixed size and truncate instead.
 */
export default ({ title, copyOnClick, icon, tone, className, children }: StatBlockProps) => {
    const key = tone ?? 'default';

    return (
        <CopyOnClick text={copyOnClick}>
            <div
                className={classNames(
                    'flex items-center gap-3.5 px-4 py-4 rounded-lg',
                    'border border-gray-700/70 bg-surface',
                    'transition-colors duration-150 hover:border-gray-600',
                    className
                )}
            >
                <div
                    className={classNames(
                        'flex-none flex items-center justify-center w-10 h-10 rounded-lg',
                        'transition-colors duration-500',
                        iconTone[key]
                    )}
                >
                    <Icon icon={icon} className={'w-5 h-5'} />
                </div>
                <div className={'min-w-0'}>
                    <p className={'font-header text-xs font-bold uppercase tracking-wider text-gray-400'}>
                        {title}
                    </p>
                    <div className={classNames('mt-1 text-base lg:text-lg font-semibold truncate', valueTone[key])}>
                        {children}
                    </div>
                </div>
            </div>
        </CopyOnClick>
    );
};
