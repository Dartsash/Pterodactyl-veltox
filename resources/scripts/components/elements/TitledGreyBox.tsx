import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <div css={tw`rounded-lg bg-surface border border-gray-700`} className={className}>
        <div css={tw`bg-raised rounded-t-lg px-4 py-3 border-b border-gray-700`}>
            {typeof title === 'string' ? (
                <p css={tw`text-2xs font-bold uppercase tracking-wider text-gray-400`}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2 text-gray-500`} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-4`}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
