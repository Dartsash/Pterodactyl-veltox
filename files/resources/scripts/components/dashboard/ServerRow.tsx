import React, { memo, useEffect, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEthernet, faHdd, faMemory, faMicrochip, faServer } from '@fortawesome/free-solid-svg-icons';
import { Link } from 'react-router-dom';
import { Server } from '@/api/server/getServer';
import getServerResourceUsage, { ServerPowerState, ServerStats } from '@/api/server/getServerResourceUsage';
import { bytesToString, ip, mbToBytes } from '@/lib/formatters';
import tw from 'twin.macro';
import Spinner from '@/components/elements/Spinner';
import styled from 'styled-components/macro';
import isEqual from 'react-fast-compare';

// Determines if the current value is in an alarm threshold so we can show it in red rather
// than the more faded default style.
const isAlarmState = (current: number, limit: number): boolean => limit > 0 && current / (limit * 1024 * 1024) >= 0.9;

const Card = styled(Link)<{ $status: ServerPowerState | undefined }>`
    ${tw`block relative overflow-hidden rounded-lg bg-neutral-800 p-5 no-underline transition-all duration-150`};
    border: 1px solid hsl(255, 19%, 23%);

    &:hover {
        ${tw`bg-neutral-700`};
        border-color: #7c4dff;
        transform: translateY(-2px);
        box-shadow: 0 10px 30px -12px rgba(124, 77, 255, 0.55);
    }

    /* Thin colour strip along the top edge showing the power state. */
    &::before {
        content: '';
        ${tw`absolute top-0 left-0 w-full`};
        height: 3px;
        ${({ $status }) =>
            !$status || $status === 'offline'
                ? tw`bg-red-500`
                : $status === 'running'
                ? tw`bg-green-500`
                : tw`bg-yellow-500`};
    }
`;

const StatusDot = styled.span<{ $status: ServerPowerState | undefined }>`
    ${tw`inline-block rounded-full mr-2`};
    width: 8px;
    height: 8px;
    ${({ $status }) =>
        !$status || $status === 'offline'
            ? tw`bg-red-500`
            : $status === 'running'
            ? tw`bg-green-500`
            : tw`bg-yellow-500`};
`;

const Bar = styled.div<{ $percent: number; $alarm: boolean }>`
    ${tw`w-full rounded-full overflow-hidden mt-1`};
    height: 4px;
    background-color: hsl(257, 23%, 17%);

    &::after {
        content: '';
        ${tw`block h-full rounded-full transition-all duration-250`};
        width: ${({ $percent }) => Math.min(100, Math.max(0, $percent))}%;
        background-color: ${({ $alarm }) => ($alarm ? '#f56565' : '#7c4dff')};
    }
`;

const Icon = memo(
    styled(FontAwesomeIcon)<{ $alarm: boolean }>`
        ${(props) => (props.$alarm ? tw`text-red-400` : tw`text-neutral-400`)};
    `,
    isEqual
);

type Timer = ReturnType<typeof setInterval>;

export default ({ server, className }: { server: Server; className?: string }) => {
    const interval = useRef<Timer>(null) as React.MutableRefObject<Timer>;
    const [isSuspended, setIsSuspended] = useState(server.status === 'suspended');
    const [stats, setStats] = useState<ServerStats | null>(null);

    const getStats = () =>
        getServerResourceUsage(server.uuid)
            .then((data) => setStats(data))
            .catch((error) => console.error(error));

    useEffect(() => {
        setIsSuspended(stats?.isSuspended || server.status === 'suspended');
    }, [stats?.isSuspended, server.status]);

    useEffect(() => {
        // Don't waste a HTTP request if there is nothing important to show to the user because
        // the server is suspended.
        if (isSuspended || server.isNodeUnderMaintenance) return;

        getStats().then(() => {
            interval.current = setInterval(() => getStats(), 30000);
        });

        return () => {
            interval.current && clearInterval(interval.current);
        };
    }, [isSuspended, server.isNodeUnderMaintenance]);

    const alarms = { cpu: false, memory: false, disk: false };
    if (stats) {
        alarms.cpu = server.limits.cpu === 0 ? false : stats.cpuUsagePercent >= server.limits.cpu * 0.9;
        alarms.memory = isAlarmState(stats.memoryUsageInBytes, server.limits.memory);
        alarms.disk = server.limits.disk === 0 ? false : isAlarmState(stats.diskUsageInBytes, server.limits.disk);
    }

    const diskLimit = server.limits.disk !== 0 ? bytesToString(mbToBytes(server.limits.disk)) : 'Unlimited';
    const memoryLimit = server.limits.memory !== 0 ? bytesToString(mbToBytes(server.limits.memory)) : 'Unlimited';
    const cpuLimit = server.limits.cpu !== 0 ? server.limits.cpu + ' %' : 'Unlimited';

    const percent = (used: number, limitInMb: number) => (limitInMb > 0 ? (used / mbToBytes(limitInMb)) * 100 : 0);

    const allocation = server.allocations.find((alloc) => alloc.isDefault);

    const statusLabel = isSuspended
        ? server.status === 'suspended'
            ? 'Suspended'
            : 'Connection Error'
        : server.isNodeUnderMaintenance
        ? 'Under Maintenance'
        : server.isTransferring
        ? 'Transferring'
        : server.status === 'installing'
        ? 'Installing'
        : server.status === 'restoring_backup'
        ? 'Restoring Backup'
        : stats
        ? stats.status === 'running'
            ? 'Online'
            : stats.status === 'offline' || !stats.status
            ? 'Offline'
            : 'Starting'
        : null;

    return (
        <Card to={`/server/${server.id}`} className={className} $status={stats?.status}>
            <div css={tw`flex items-start justify-between`}>
                <div css={tw`flex items-start min-w-0`}>
                    <div
                        css={tw`flex items-center justify-center rounded-lg mr-3 flex-shrink-0`}
                        style={{ width: 40, height: 40, backgroundColor: 'rgba(124, 77, 255, 0.15)' }}
                    >
                        <FontAwesomeIcon icon={faServer} css={tw`text-brand-400`} />
                    </div>
                    <div css={tw`min-w-0`}>
                        <p css={tw`text-lg text-neutral-50 font-medium break-words leading-tight`}>{server.name}</p>
                        {!!server.description && (
                            <p css={tw`text-xs text-neutral-400 break-words line-clamp-2 mt-1`}>{server.description}</p>
                        )}
                    </div>
                </div>
                {statusLabel && (
                    <span css={tw`flex items-center text-xs text-neutral-300 uppercase ml-3 flex-shrink-0`}>
                        <StatusDot $status={stats?.status} />
                        {statusLabel}
                    </span>
                )}
            </div>

            {!!allocation && (
                <div css={tw`flex items-center mt-4 text-sm text-neutral-300`}>
                    <FontAwesomeIcon icon={faEthernet} css={tw`text-neutral-400 mr-2`} />
                    {allocation.alias || ip(allocation.ip)}:{allocation.port}
                </div>
            )}

            <div css={tw`mt-4`}>
                {!stats || isSuspended || server.isNodeUnderMaintenance ? (
                    isSuspended || server.isNodeUnderMaintenance || server.status ? (
                        <p css={tw`text-xs text-neutral-400`}>Resource usage is unavailable right now.</p>
                    ) : (
                        <Spinner size={'small'} />
                    )
                ) : (
                    <div css={tw`grid grid-cols-3 gap-4`}>
                        <div>
                            <div css={tw`flex items-center text-sm`}>
                                <Icon icon={faMicrochip} $alarm={alarms.cpu} css={tw`mr-2`} />
                                <span css={tw`text-neutral-100`}>{stats.cpuUsagePercent.toFixed(1)} %</span>
                            </div>
                            <Bar $percent={server.limits.cpu > 0 ? (stats.cpuUsagePercent / server.limits.cpu) * 100 : 0} $alarm={alarms.cpu} />
                            <p css={tw`text-2xs text-neutral-500 mt-1`}>of {cpuLimit}</p>
                        </div>
                        <div>
                            <div css={tw`flex items-center text-sm`}>
                                <Icon icon={faMemory} $alarm={alarms.memory} css={tw`mr-2`} />
                                <span css={tw`text-neutral-100`}>{bytesToString(stats.memoryUsageInBytes)}</span>
                            </div>
                            <Bar $percent={percent(stats.memoryUsageInBytes, server.limits.memory)} $alarm={alarms.memory} />
                            <p css={tw`text-2xs text-neutral-500 mt-1`}>of {memoryLimit}</p>
                        </div>
                        <div>
                            <div css={tw`flex items-center text-sm`}>
                                <Icon icon={faHdd} $alarm={alarms.disk} css={tw`mr-2`} />
                                <span css={tw`text-neutral-100`}>{bytesToString(stats.diskUsageInBytes)}</span>
                            </div>
                            <Bar $percent={percent(stats.diskUsageInBytes, server.limits.disk)} $alarm={alarms.disk} />
                            <p css={tw`text-2xs text-neutral-500 mt-1`}>of {diskLimit}</p>
                        </div>
                    </div>
                )}
            </div>
        </Card>
    );
};
