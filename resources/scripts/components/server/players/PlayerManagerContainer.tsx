import React, { useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBan,
    faCrown,
    faExclamationTriangle,
    faInfoCircle,
    faNetworkWired,
    faPlus,
    faSearch,
    faTrashAlt,
    faUserCheck,
    faUserShield,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Spinner from '@/components/elements/Spinner';
import Button from '@/components/elements/Button';
import FlashMessageRender from '@/components/FlashMessageRender';
import ConfirmationModal from '@/components/elements/ConfirmationModal';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import getPlayers, {
    addPlayer,
    PlayerEntry,
    PlayerList,
    PlayerManagerData,
    removePlayer,
} from '@/api/server/players/getPlayers';

const FLASH_KEY = 'server:players';

const listIcon = (key: string) => {
    switch (key) {
        case 'ops':
            return faCrown;
        case 'banned-players':
            return faBan;
        case 'banned-ips':
            return faNetworkWired;
        default:
            return faUserCheck;
    }
};

const listColor = (key: string) => {
    switch (key) {
        case 'ops':
            return tw`bg-yellow-600`;
        case 'banned-players':
        case 'banned-ips':
            return tw`bg-red-500`;
        default:
            return tw`bg-primary-500`;
    }
};

/**
 * Operator levels as the game documents them, so the picker explains itself
 * instead of showing four bare numbers.
 */
const LEVELS: Array<{ value: number; label: string }> = [
    { value: 1, label: '1 — bypass spawn protection' },
    { value: 2, label: '2 — world commands and cheats' },
    { value: 3, label: '3 — kick, ban and op players' },
    { value: 4, label: '4 — full access, including stop' },
];

/**
 * Head render for a player. Falls back to a plain initial when the avatar
 * service cannot be reached, so an offline panel still looks fine.
 */
const AVATAR_HOST = 'https://mc-heads.net/avatar/';

const Avatar = ({ entry }: { entry: PlayerEntry }) => {
    const [failed, setFailed] = useState(false);

    if (failed || !entry.name) {
        return (
            <div
                css={tw`w-8 h-8 flex-none rounded flex items-center justify-center bg-canvas text-gray-300 text-sm uppercase`}
            >
                {(entry.name || '?').charAt(0)}
            </div>
        );
    }

    // The uuid renders the correct skin even after a name change; the name is
    // only used when the file has no uuid stored for the entry.
    const source = AVATAR_HOST + encodeURIComponent(entry.uuid || entry.name) + '/32';

    return (
        <img
            src={source}
            alt={''}
            onError={() => setFailed(true)}
            css={tw`w-8 h-8 flex-none rounded bg-canvas`}
        />
    );
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addError, addFlash, clearFlashes } = useFlash();

    const [data, setData] = useState<PlayerManagerData | null>(null);
    const [loading, setLoading] = useState(true);
    const [active, setActive] = useState<string>('');
    const [query, setQuery] = useState('');

    // Add form state.
    const [target, setTarget] = useState('');
    const [reason, setReason] = useState('');
    const [level, setLevel] = useState(4);
    const [bypass, setBypass] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    // Entry queued for removal, shown in the confirmation dialog.
    const [removing, setRemoving] = useState<PlayerEntry | null>(null);
    const [working, setWorking] = useState(false);

    useEffect(() => {
        clearFlashes(FLASH_KEY);

        getPlayers(uuid)
            .then((result) => {
                setData(result);
                setActive(result.lists[0]?.key || '');
            })
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const list: PlayerList | undefined = useMemo(
        () => data?.lists.find((item) => item.key === active),
        [data, active]
    );

    const entries = useMemo(() => {
        const rows = (data?.entries[active] || []).slice();

        if (query.trim() === '') {
            return rows;
        }

        const needle = query.trim().toLowerCase();

        return rows.filter(
            (entry) =>
                entry.name.toLowerCase().includes(needle) ||
                (entry.reason || '').toLowerCase().includes(needle) ||
                (entry.uuid || '').toLowerCase().includes(needle)
        );
    }, [data, active, query]);

    /**
     * Names already seen on the server that are not on the current list yet, so
     * the datalist only ever suggests something useful.
     */
    const suggestions = useMemo(() => {
        if (!data || !list || list.subject === 'ip') {
            return [];
        }

        const present = (data.entries[list.key] || []).map((entry) => entry.name.toLowerCase());

        return data.knownPlayers.filter((name) => !present.includes(name.toLowerCase()));
    }, [data, list]);

    const replaceEntries = (key: string, rows: PlayerEntry[], running: boolean) => {
        setData((current) =>
            current === null ? current : { ...current, running, entries: { ...current.entries, [key]: rows } }
        );
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        if (!list || target.trim() === '' || submitting) {
            return;
        }

        setSubmitting(true);
        clearFlashes(FLASH_KEY);

        addPlayer(uuid, list.key, {
            target: target.trim(),
            reason: list.supportsReason ? reason : null,
            level: list.supportsLevel ? level : null,
            bypassesPlayerLimit: list.supportsLevel ? bypass : false,
        })
            .then((result) => {
                replaceEntries(result.key, result.entries, result.running);
                setTarget('');
                setReason('');
                setBypass(false);

                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    message: result.running
                        ? `${target.trim()} was updated on the running server.`
                        : `${target.trim()} was written to ${list.file}.`,
                });
            })
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => setSubmitting(false));
    };

    const confirmRemoval = () => {
        if (!list || !removing) {
            return;
        }

        setWorking(true);
        clearFlashes(FLASH_KEY);

        removePlayer(uuid, list.key, removing.target)
            .then((result) => {
                replaceEntries(result.key, result.entries, result.running);

                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    message: `${removing.name} was removed from ${list.name.toLowerCase()}.`,
                });
            })
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => {
                setWorking(false);
                setRemoving(null);
            });
    };

    return (
        <ServerContentBlock title={'Players'}>
            <FlashMessageRender byKey={FLASH_KEY} css={tw`mb-4`} />

            <ConfirmationModal
                visible={removing !== null}
                title={`${list?.removeLabel || 'Remove'} ${removing?.name || ''}?`}
                buttonText={list?.removeLabel || 'Remove'}
                showSpinnerOverlay={working}
                onConfirmed={confirmRemoval}
                onModalDismissed={() => setRemoving(null)}
            >
                {data?.running ? (
                    <>
                        This runs the matching console command right away, so the change takes effect on the running
                        server.
                    </>
                ) : (
                    <>
                        The server is stopped, so <code>{list?.file}</code> is edited directly. The change applies the
                        next time the server starts.
                    </>
                )}
            </ConfirmationModal>

            <div css={tw`flex flex-wrap items-center justify-between mb-6`}>
                <div css={tw`mb-4 sm:mb-0`}>
                    <h1 css={tw`text-2xl text-gray-100`}>Players</h1>
                    <p css={tw`text-sm text-gray-400 mt-1`}>
                        Manage the whitelist, operators and bans without editing a single JSON file.
                    </p>
                </div>
                <div css={tw`relative w-full sm:w-72`}>
                    <div css={tw`absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none`}>
                        <FontAwesomeIcon icon={faSearch} />
                    </div>
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={'Search this list...'}
                        css={tw`pl-10`}
                    />
                </div>
            </div>

            {loading ? (
                <Spinner size={'large'} centered />
            ) : !data || data.lists.length === 0 ? (
                <p css={tw`text-center text-sm text-gray-400 py-16`}>
                    No player lists have been made available on this panel.
                </p>
            ) : (
                <>
                    <div
                        css={tw`flex items-start p-4 mb-6 rounded-lg bg-surface border border-gray-700 text-sm text-gray-300`}
                    >
                        <FontAwesomeIcon
                            icon={data.running ? faInfoCircle : faExclamationTriangle}
                            css={[tw`mt-0.5 mr-3 flex-none`, data.running ? tw`text-primary-400` : tw`text-yellow-500`]}
                        />
                        <span>
                            {data.running ? (
                                <>
                                    The server is running, so every change is sent as a console command and applies
                                    immediately.
                                </>
                            ) : (
                                <>
                                    The server is stopped. Changes are written straight into the JSON files and apply on
                                    the next start.
                                </>
                            )}
                        </span>
                    </div>

                    <div css={tw`flex flex-wrap gap-2 mb-6`}>
                        {data.lists.map((item) => {
                            const count = (data.entries[item.key] || []).length;

                            return (
                                <button
                                    key={item.key}
                                    onClick={() => {
                                        setActive(item.key);
                                        setQuery('');
                                        setTarget('');
                                        setReason('');
                                    }}
                                    css={[
                                        tw`inline-flex items-center px-4 py-1.5 rounded-full text-sm font-medium transition-colors duration-150`,
                                        active === item.key
                                            ? tw`bg-primary-500 text-white`
                                            : tw`bg-raised text-gray-300 hover:bg-gray-700`,
                                    ]}
                                >
                                    <FontAwesomeIcon icon={listIcon(item.key)} css={tw`mr-2 text-xs`} />
                                    {item.name}
                                    <span css={tw`ml-2 text-2xs opacity-75`}>{count}</span>
                                </button>
                            );
                        })}
                    </div>

                    {list && (
                        <div css={tw`grid grid-cols-1 lg:grid-cols-3 gap-6`}>
                            <div css={tw`lg:col-span-1`}>
                                <form
                                    onSubmit={submit}
                                    css={tw`bg-surface border border-gray-700 rounded-lg p-4 lg:sticky lg:top-4`}
                                >
                                    <div css={tw`flex items-center mb-4`}>
                                        <div
                                            css={[
                                                tw`w-10 h-10 flex-none rounded-lg flex items-center justify-center text-white shadow-md`,
                                                listColor(list.key),
                                            ]}
                                        >
                                            <FontAwesomeIcon icon={listIcon(list.key)} />
                                        </div>
                                        <div css={tw`ml-3 min-w-0`}>
                                            <p css={tw`text-gray-100 font-medium leading-tight`}>{list.name}</p>
                                            <p css={tw`text-xs text-gray-500 truncate`}>{list.file}</p>
                                        </div>
                                    </div>

                                    <p css={tw`text-sm text-gray-400 mb-4 leading-relaxed`}>{list.description}</p>

                                    <label css={tw`block text-xs uppercase tracking-wide text-gray-400 mb-1`}>
                                        {list.subject === 'ip' ? 'IP address' : 'Player name'}
                                    </label>
                                    <Input
                                        value={target}
                                        onChange={(e) => setTarget(e.target.value)}
                                        placeholder={list.subject === 'ip' ? '203.0.113.42' : 'Notch'}
                                        list={list.subject === 'ip' ? undefined : `known-players-${list.key}`}
                                        maxLength={64}
                                    />
                                    {list.subject !== 'ip' && suggestions.length > 0 && (
                                        <datalist id={`known-players-${list.key}`}>
                                            {suggestions.map((name) => (
                                                <option key={name} value={name} />
                                            ))}
                                        </datalist>
                                    )}

                                    {list.supportsLevel && (
                                        <>
                                            <label
                                                css={tw`block text-xs uppercase tracking-wide text-gray-400 mb-1 mt-4`}
                                            >
                                                Operator level
                                            </label>
                                            <Select
                                                value={String(level)}
                                                onChange={(e) => setLevel(Number(e.currentTarget.value))}
                                            >
                                                {LEVELS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </Select>

                                            <label css={tw`mt-3 flex items-start cursor-pointer`}>
                                                <Input
                                                    type={'checkbox'}
                                                    checked={bypass}
                                                    onChange={(e) => setBypass(e.currentTarget.checked)}
                                                    css={tw`mt-1 mr-3`}
                                                />
                                                <span css={tw`text-sm text-gray-300`}>
                                                    Can join a full server
                                                    <span css={tw`block text-xs text-gray-500`}>
                                                        Ignores the player limit from server.properties.
                                                    </span>
                                                </span>
                                            </label>
                                        </>
                                    )}

                                    {list.supportsReason && (
                                        <>
                                            <label
                                                css={tw`block text-xs uppercase tracking-wide text-gray-400 mb-1 mt-4`}
                                            >
                                                Reason
                                            </label>
                                            <Input
                                                value={reason}
                                                onChange={(e) => setReason(e.target.value)}
                                                placeholder={'Griefing spawn'}
                                                maxLength={200}
                                            />
                                            <p css={tw`text-xs text-gray-500 mt-1`}>
                                                Shown to the player on the disconnect screen. Optional.
                                            </p>
                                        </>
                                    )}

                                    <Button
                                        type={'submit'}
                                        css={tw`mt-4 w-full`}
                                        disabled={target.trim() === ''}
                                        isLoading={submitting}
                                    >
                                        <FontAwesomeIcon icon={faPlus} css={tw`mr-2 text-xs`} />
                                        {list.addLabel}
                                    </Button>
                                </form>
                            </div>

                            <div css={tw`lg:col-span-2`}>
                                {entries.length === 0 ? (
                                    <div
                                        css={tw`flex flex-col items-center justify-center text-center bg-surface border border-gray-700 rounded-lg py-16 px-4`}
                                    >
                                        <FontAwesomeIcon icon={faUserShield} css={tw`text-3xl text-gray-600 mb-3`} />
                                        <p css={tw`text-sm text-gray-400`}>
                                            {query.trim() !== ''
                                                ? 'Nothing on this list matches your search.'
                                                : `${list.name} is empty right now.`}
                                        </p>
                                    </div>
                                ) : (
                                    <div css={tw`space-y-2`}>
                                        {entries.map((entry) => (
                                            <div
                                                key={`${list.key}-${entry.target}`}
                                                css={tw`flex items-center bg-surface border border-gray-700 rounded-lg p-3 transition-colors duration-150 hover:border-gray-600`}
                                            >
                                                {list.subject === 'ip' ? (
                                                    <div
                                                        css={tw`w-8 h-8 flex-none rounded flex items-center justify-center bg-canvas text-gray-400`}
                                                    >
                                                        <FontAwesomeIcon icon={faNetworkWired} css={tw`text-xs`} />
                                                    </div>
                                                ) : (
                                                    <Avatar entry={entry} />
                                                )}

                                                <div css={tw`ml-3 min-w-0 flex-1`}>
                                                    <p css={tw`text-sm text-gray-100 truncate`}>
                                                        {entry.name}
                                                        {entry.level !== null && (
                                                            <span
                                                                css={tw`ml-2 text-2xs uppercase tracking-wide text-yellow-500`}
                                                            >
                                                                level {entry.level}
                                                            </span>
                                                        )}
                                                        {entry.bypassesPlayerLimit && (
                                                            <span css={tw`ml-2 text-2xs text-gray-400`}>
                                                                bypasses limit
                                                            </span>
                                                        )}
                                                    </p>
                                                    {entry.reason ? (
                                                        <p css={tw`text-xs text-gray-500 truncate`}>
                                                            {entry.reason}
                                                            {entry.source && <> · by {entry.source}</>}
                                                            {entry.created && <> · {entry.created}</>}
                                                        </p>
                                                    ) : (
                                                        entry.uuid && (
                                                            <p css={tw`text-xs text-gray-600 font-mono truncate`}>
                                                                {entry.uuid}
                                                            </p>
                                                        )
                                                    )}
                                                </div>

                                                <button
                                                    type={'button'}
                                                    onClick={() => setRemoving(entry)}
                                                    title={list.removeLabel}
                                                    css={tw`ml-3 flex-none p-2 rounded text-gray-500 transition-colors duration-150 hover:text-red-400 hover:bg-canvas focus:outline-none`}
                                                >
                                                    <FontAwesomeIcon icon={faTrashAlt} />
                                                </button>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </>
            )}
        </ServerContentBlock>
    );
};
