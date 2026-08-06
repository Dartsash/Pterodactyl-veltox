import React from 'react';
import classNames from 'classnames';

interface CodeProps {
    dark?: boolean | undefined;
    className?: string;
    children: React.ReactChild | React.ReactFragment | React.ReactPortal;
}

export default ({ dark, className, children }: CodeProps) => (
    <code
        className={classNames('font-mono text-sm px-2 py-1 inline-block rounded border', className, {
            'bg-raised border-gray-700 text-gray-100': !dark,
            'bg-canvas border-gray-800 text-gray-100': dark,
        })}
    >
        {children}
    </code>
);
