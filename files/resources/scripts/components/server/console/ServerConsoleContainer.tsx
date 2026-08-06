import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';
import CopyOnClick from '@/components/elements/CopyOnClick';
import Icon from '@/components/elements/Icon';
import { faWifi } from '@fortawesome/free-solid-svg-icons';
import { ip } from '@/lib/formatters';
import { capitalize } from '@/lib/strings';
import classNames from 'classnames';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const StatusBadge = ({ status }: { status: string | null }) => {
    const online = status === 'running';
    const offline = status === null || status === 'offline';

    return (
        <span
            className={classNames(
                'flex-none inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full',
                'text-2xs font-bold uppercase tracking-wider ring-1 ring-inset',
                {
                    'bg-success-500/10 text-success-400 ring-success-500/25': online,
                    'bg-gray-500/10 text-gray-300 ring-gray-500/25': offline,
                    'bg-warning-500/10 text-warning-400 ring-warning-500/25': !online && !offline,
                }
            )}
        >
            <span
                className={classNames('w-1.5 h-1.5 rounded-full', {
                    'bg-success-400': online,
                    'bg-gray-400': offline,
                    'bg-warning-400': !online && !offline,
                })}
            />
            {status === null ? 'Connecting' : online ? 'Online' : capitalize(status)}
        </span>
    );
};

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);

    const allocation = ServerContext.useStoreState((state) => {
        const match = state.server.data!.allocations.find((allocation) => allocation.isDefault);

        return !match ? null : `${match.alias || ip(match.ip)}:${match.port}`;
    });

    return (
        <ServerContentBlock title={'Console'}>
            {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                <Alert type={'warning'} className={'mb-4'}>
                    {isNodeUnderMaintenance
                        ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                        : isInstalling
                        ? 'This server is currently running its installation process and most actions are unavailable.'
                        : 'This server is currently being transferred to another node and all actions are unavailable.'}
                </Alert>
            )}

            {/*
              The old header hid the server name entirely below the `sm` breakpoint
              (`hidden sm:block`). This stacks instead, so the name is always visible
              and the power buttons drop to full width on narrow screens.
            */}
            <div className={'flex flex-col gap-4 mb-5 sm:flex-row sm:items-center'}>
                <div className={'min-w-0 flex-1'}>
                    <h1
                        className={
                            'flex items-center gap-3 font-header font-semibold text-2xl text-gray-50 leading-tight'
                        }
                    >
                        <span className={'truncate'}>{name}</span>
                        <StatusBadge status={status} />
                    </h1>
                    {description && <p className={'mt-1.5 text-sm text-gray-300 line-clamp-2'}>{description}</p>}
                    {allocation && (
                        <CopyOnClick text={allocation}>
                            <button
                                type={'button'}
                                title={'Copy the server address'}
                                className={classNames(
                                    'mt-3 inline-flex items-center gap-2 px-2.5 py-1.5 rounded-md',
                                    'border border-gray-700 bg-surface font-mono text-xs text-gray-300',
                                    'transition-colors duration-150 hover:text-gray-50 hover:border-gray-600',
                                    'focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2',
                                    'focus:ring-offset-canvas'
                                )}
                            >
                                <Icon icon={faWifi} className={'w-3 h-3'} />
                                {allocation}
                            </button>
                        </CopyOnClick>
                    )}
                </div>
                <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                    <PowerButtons className={'flex flex-none gap-2 sm:justify-end'} />
                </Can>
            </div>

            <div className={'grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_20rem] gap-4 mb-4'}>
                <div className={'flex min-w-0'}>
                    <Spinner.Suspense>
                        <Console />
                    </Spinner.Suspense>
                </div>
                <ServerDetailsBlock className={'order-last lg:order-none'} />
            </div>

            <div className={'grid grid-cols-1 md:grid-cols-3 gap-4'}>
                <Spinner.Suspense>
                    <StatGraphs />
                </Spinner.Suspense>
            </div>

            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
