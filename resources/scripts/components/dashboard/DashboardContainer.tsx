import React, { useEffect, useMemo, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faHdd, faLayerGroup, faMemory, faMicrochip, faSearch } from '@fortawesome/free-solid-svg-icons';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import AnnouncementBanner from '@/components/dashboard/AnnouncementBanner';

// A single figure in the summary strip at the top of the dashboard.
const StatCard = styled.div`
    ${tw`flex items-center rounded-lg p-4`};
    background-color: hsl(257, 23%, 17%);
    border: 1px solid hsl(255, 19%, 23%);
`;

const SearchBox = styled.input`
    ${tw`w-full rounded-lg py-2 pl-10 pr-4 text-sm text-neutral-100 transition-all duration-150`};
    background-color: hsl(257, 23%, 17%);
    border: 1px solid hsl(255, 19%, 23%);

    &::placeholder {
        ${tw`text-neutral-400`};
    }

    &:focus {
        outline: none;
        border-color: #7c4dff;
        box-shadow: 0 0 0 3px rgba(124, 77, 255, 0.25);
    }
`;

const greetingFor = (hour: number): string => {
    if (hour < 5) return 'Good night';
    if (hour < 12) return 'Good morning';
    if (hour < 18) return 'Good afternoon';

    return 'Good evening';
};

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const [term, setTerm] = useState('');
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined }),
    );

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        // Don't use react-router to handle changing this part of the URL, otherwise it
        // triggers a needless re-render. We just want to track this in the URL incase the
        // user refreshes the page.
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    // Totals shown in the summary strip. Calculated from the servers on this page,
    // so no extra API requests are made.
    const totals = useMemo(() => {
        const items = servers?.items || [];

        return {
            memory: items.reduce((sum, server) => sum + server.limits.memory, 0),
            disk: items.reduce((sum, server) => sum + server.limits.disk, 0),
            cpu: items.reduce((sum, server) => sum + server.limits.cpu, 0),
        };
    }, [servers?.items]);

    const stats = [
        {
            icon: faLayerGroup,
            label: 'Servers',
            value: String(servers?.pagination.total ?? 0),
        },
        {
            icon: faMemory,
            label: 'Memory',
            value: totals.memory > 0 ? bytesToString(mbToBytes(totals.memory)) : 'Unlimited',
        },
        {
            icon: faHdd,
            label: 'Disk',
            value: totals.disk > 0 ? bytesToString(mbToBytes(totals.disk)) : 'Unlimited',
        },
        {
            icon: faMicrochip,
            label: 'CPU',
            value: totals.cpu > 0 ? `${totals.cpu} %` : 'Unlimited',
        },
    ];

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            {/* Announcement addon banner, configured under /admin/addons */}
            <AnnouncementBanner />

            {/* Greeting header */}
            <div css={tw`flex flex-wrap items-end justify-between mb-6`}>
                <div>
                    <h1 css={tw`text-2xl sm:text-3xl font-header font-medium text-neutral-50 leading-tight`}>
                        {greetingFor(new Date().getHours())}, {username}
                    </h1>
                    <p css={tw`text-sm text-neutral-400 mt-1`}>
                        {showOnlyAdmin
                            ? 'Viewing every server on this panel.'
                            : 'Here are the servers on your account.'}
                    </p>
                </div>
                {rootAdmin && (
                    <div css={tw`flex items-center mt-4 sm:mt-0`}>
                        <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                            {showOnlyAdmin ? "Others' servers" : 'Your servers'}
                        </p>
                        <Switch
                            name={'show_all_servers'}
                            defaultChecked={showOnlyAdmin}
                            onChange={() => setShowOnlyAdmin((s) => !s)}
                        />
                    </div>
                )}
            </div>

            {/* Summary strip */}
            <div css={tw`grid gap-4 grid-cols-2 lg:grid-cols-4 mb-6`}>
                {stats.map((stat) => (
                    <StatCard key={stat.label}>
                        <div
                            css={tw`flex items-center justify-center rounded-lg mr-3 flex-shrink-0`}
                            style={{ width: 40, height: 40, backgroundColor: 'rgba(124, 77, 255, 0.15)' }}
                        >
                            <FontAwesomeIcon icon={stat.icon} css={tw`text-brand-400`} />
                        </div>
                        <div css={tw`min-w-0`}>
                            <p css={tw`text-2xs uppercase text-neutral-400 tracking-wide`}>{stat.label}</p>
                            <p css={tw`text-lg text-neutral-50 font-medium leading-tight truncate`}>{stat.value}</p>
                        </div>
                    </StatCard>
                ))}
            </div>

            {!servers ? (
                <Spinner centered size={'large'} />
            ) : (
                <>
                    {/* Filter box, only worth showing once there is something to filter. */}
                    {servers.items.length > 1 && (
                        <div css={tw`relative mb-4`}>
                            <FontAwesomeIcon
                                icon={faSearch}
                                css={tw`absolute text-neutral-400 text-sm`}
                                style={{ left: 14, top: '50%', transform: 'translateY(-50%)' }}
                            />
                            <SearchBox
                                type={'text'}
                                value={term}
                                onChange={(e) => setTerm(e.currentTarget.value)}
                                placeholder={'Filter servers by name or description...'}
                            />
                        </div>
                    )}
                    <Pagination data={servers} onPageSelect={setPage}>
                        {({ items }) => {
                            const needle = term.trim().toLowerCase();
                            const visible = !needle
                                ? items
                                : items.filter(
                                      (server) =>
                                          server.name.toLowerCase().includes(needle) ||
                                          (server.description || '').toLowerCase().includes(needle),
                                  );

                            if (!items.length) {
                                return (
                                    <p css={tw`text-center text-sm text-neutral-400`}>
                                        {showOnlyAdmin
                                            ? 'There are no other servers to display.'
                                            : 'There are no servers associated with your account.'}
                                    </p>
                                );
                            }

                            if (!visible.length) {
                                return (
                                    <p css={tw`text-center text-sm text-neutral-400`}>
                                        No servers match &quot;{term}&quot;.
                                    </p>
                                );
                            }

                            return (
                                <div css={tw`grid gap-4 md:grid-cols-2 xl:grid-cols-3`}>
                                    {visible.map((server) => (
                                        <ServerRow key={server.uuid} server={server} />
                                    ))}
                                </div>
                            );
                        }}
                    </Pagination>
                </>
            )}
        </PageContentBlock>
    );
};
