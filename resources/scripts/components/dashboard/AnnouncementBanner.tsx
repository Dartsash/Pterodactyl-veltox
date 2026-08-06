import React from 'react';
import useSWR from 'swr';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBullhorn,
    faCheckCircle,
    faExclamationTriangle,
    faTimes,
    faTimesCircle,
} from '@fortawesome/free-solid-svg-icons';
import getAnnouncement, { Announcement } from '@/api/getAnnouncement';
import { usePersistedState } from '@/plugins/usePersistedState';

type Style = { border: string; background: string; accent: string; icon: typeof faBullhorn };

const STYLES: Record<Announcement['type'], Style> = {
    info: {
        border: 'rgba(124, 77, 255, 0.45)',
        background: 'rgba(124, 77, 255, 0.12)',
        accent: '#b39dff',
        icon: faBullhorn,
    },
    success: {
        border: 'rgba(16, 185, 129, 0.45)',
        background: 'rgba(16, 185, 129, 0.12)',
        accent: '#6ee7b7',
        icon: faCheckCircle,
    },
    warning: {
        border: 'rgba(245, 158, 11, 0.45)',
        background: 'rgba(245, 158, 11, 0.12)',
        accent: '#fcd34d',
        icon: faExclamationTriangle,
    },
    danger: {
        border: 'rgba(239, 68, 68, 0.45)',
        background: 'rgba(239, 68, 68, 0.12)',
        accent: '#fca5a5',
        icon: faTimesCircle,
    },
};

const Banner = styled.div<{ $border: string; $background: string }>`
    ${tw`flex items-start rounded-lg p-4 mb-6`};
    background-color: ${(props) => props.$background};
    border: 1px solid ${(props) => props.$border};
`;

export default () => {
    const { data: announcement } = useSWR<Announcement | null>('/api/client/announcement', getAnnouncement, {
        revalidateOnFocus: false,
    });

    // Remembers the version string of the banner the user closed, so editing the
    // text in the admin area brings it back for everyone.
    const [dismissed, setDismissed] = usePersistedState<string | null>('announcement:dismissed', null);

    if (!announcement) return null;
    if (announcement.dismissible && dismissed === announcement.version) return null;

    const style = STYLES[announcement.type] || STYLES.info;

    return (
        <Banner $border={style.border} $background={style.background}>
            <FontAwesomeIcon icon={style.icon} css={tw`mt-1 mr-3 flex-shrink-0`} style={{ color: style.accent }} />
            <div css={tw`flex-1 min-w-0`}>
                {!!announcement.title && <p css={tw`text-sm font-medium text-neutral-50`}>{announcement.title}</p>}
                {!!announcement.message && (
                    <p css={tw`text-sm text-neutral-300 whitespace-pre-line`}>{announcement.message}</p>
                )}
            </div>
            {announcement.dismissible && (
                <button
                    type={'button'}
                    onClick={() => setDismissed(announcement.version)}
                    css={tw`ml-3 text-neutral-400 hover:text-neutral-100 transition-colors duration-150`}
                    aria-label={'Dismiss announcement'}
                >
                    <FontAwesomeIcon icon={faTimes} />
                </button>
            )}
        </Banner>
    );
};
