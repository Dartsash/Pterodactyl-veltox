import React, { useEffect, useMemo, useState } from 'react';
import {
    faClock,
    faCloudDownloadAlt,
    faCloudUploadAlt,
    faHdd,
    faMemory,
    faMicrochip,
} from '@fortawesome/free-solid-svg-icons';
import { bytesToString, mbToBytes } from '@/lib/formatters';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import UptimeDuration from '@/components/server/UptimeDuration';
import StatBlock, { StatTone } from '@/components/server/console/StatBlock';
import useWebsocketEvent from '@/plugins/useWebsocketEvent';
import classNames from 'classnames';
import { capitalize } from '@/lib/strings';

type Stats = Record<'memory' | 'cpu' | 'disk' | 'uptime' | 'rx' | 'tx', number>;

const getTone = (value: number, max: number | null): StatTone => {
    const delta = !max ? 0 : value / max;

    if (delta > 0.9) {
        return 'danger';
    }

    if (delta > 0.8) {
        return 'warning';
    }

    return undefined;
};

const Limit = ({ limit, children }: { limit: string | null; children: React.ReactNode }) => (
    <>
        {children}
        <span className={'ml-1 text-gray-400 text-[80%] font-medium select-none'}>/ {limit || <>&infin;</>}</span>
    </>
);

const Offline = () => <span className={'text-gray-400 font-medium'}>Offline</span>;

/**
 * The metric rail next to the console.
 *
 * The server address used to live here as a seventh block. It moved up into the
 * page header: it is the most-used piece of information on the page and it is
 * not a metric, so it does not belong in a list of live readings.
 */
const ServerDetailsBlock = ({ className }: { className?: string }) => {
    const [stats, setStats] = useState<Stats>({ memory: 0, cpu: 0, disk: 0, uptime: 0, tx: 0, rx: 0 });

    const status = ServerContext.useStoreState((state) => state.status.value);
    const connected = ServerContext.useStoreState((state) => state.socket.connected);
    const instance = ServerContext.useStoreState((state) => state.socket.instance);
    const limits = ServerContext.useStoreState((state) => state.server.data!.limits);

    const textLimits = useMemo(
        () => ({
            cpu: limits?.cpu ? `${limits.cpu}%` : null,
            memory: limits?.memory ? bytesToString(mbToBytes(limits.memory)) : null,
            disk: limits?.disk ? bytesToString(mbToBytes(limits.disk)) : null,
        }),
        [limits]
    );

    useEffect(() => {
        if (!connected || !instance) {
            return;
        }

        instance.send(SocketRequest.SEND_STATS);
    }, [instance, connected]);

    useWebsocketEvent(SocketEvent.STATS, (data) => {
        let stats: any = {};
        try {
            stats = JSON.parse(data);
        } catch (e) {
            return;
        }

        setStats({
            memory: stats.memory_bytes,
            cpu: stats.cpu_absolute,
            disk: stats.disk_bytes,
            tx: stats.network.tx_bytes,
            rx: stats.network.rx_bytes,
            uptime: stats.uptime || 0,
        });
    });

    return (
        <div className={classNames('grid grid-cols-2 gap-2.5 lg:grid-cols-1 lg:grid-rows-6', className)}>
            <StatBlock
                icon={faClock}
                title={'Uptime'}
                // A stopped server is a normal state, not a fault. Only the
                // transitional states (starting / stopping) get a warning tone.
                tone={status === null || status === 'offline' || status === 'running' ? undefined : 'warning'}
            >
                {status === null ? (
                    'Offline'
                ) : stats.uptime > 0 ? (
                    <UptimeDuration uptime={stats.uptime / 1000} />
                ) : (
                    capitalize(status)
                )}
            </StatBlock>
            <StatBlock icon={faMicrochip} title={'CPU Load'} tone={getTone(stats.cpu, limits.cpu)}>
                {status === 'offline' ? <Offline /> : <Limit limit={textLimits.cpu}>{stats.cpu.toFixed(2)}%</Limit>}
            </StatBlock>
            <StatBlock icon={faMemory} title={'Memory'} tone={getTone(stats.memory / 1024, limits.memory * 1024)}>
                {status === 'offline' ? (
                    <Offline />
                ) : (
                    <Limit limit={textLimits.memory}>{bytesToString(stats.memory)}</Limit>
                )}
            </StatBlock>
            <StatBlock icon={faHdd} title={'Disk'} tone={getTone(stats.disk / 1024, limits.disk * 1024)}>
                <Limit limit={textLimits.disk}>{bytesToString(stats.disk)}</Limit>
            </StatBlock>
            <StatBlock icon={faCloudDownloadAlt} title={'Network (Inbound)'}>
                {status === 'offline' ? <Offline /> : bytesToString(stats.rx)}
            </StatBlock>
            <StatBlock icon={faCloudUploadAlt} title={'Network (Outbound)'}>
                {status === 'offline' ? <Offline /> : bytesToString(stats.tx)}
            </StatBlock>
        </div>
    );
};

export default ServerDetailsBlock;
