import React, { useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faDownload,
    faPuzzlePiece,
    faSearch,
    faSpinner,
    faStar,
    faSync,
    faTrash,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import getAddons, { Addon } from '@/api/server/addons/getAddons';
import getAddonVersions, { AddonVersion } from '@/api/server/addons/getAddonVersions';
import installAddon from '@/api/server/addons/installAddon';
import uninstallAddon from '@/api/server/addons/uninstallAddon';
import setAddonState from '@/api/server/addons/setAddonState';

const categories = ['All', 'Plugin', 'Mod', 'Datapack'] as const;
type Category = typeof categories[number];

const badgeColor = (category: Addon['category']) => {
    switch (category) {
        case 'Mod':
            return tw`bg-success-500`;
        case 'Datapack':
            return tw`bg-cyan-500`;
        default:
            return tw`bg-primary-500`;
    }
};

/**
 * "5.4.150 · MC 1.16.5-1.21.4 (beta)" — keeps the dropdown readable without
 * dumping the full game version array into it.
 */
const versionLabel = (version: AddonVersion): string => {
    let label = version.version;

    if (version.gameVersions.length === 1) {
        label += ` · MC ${version.gameVersions[0]}`;
    } else if (version.gameVersions.length > 1) {
        label += ` · MC ${version.gameVersions[0]}-${version.gameVersions[version.gameVersions.length - 1]}`;
    }

    if (version.prerelease) {
        label += ' (beta)';
    }

    return label;
};

const Switch = ({
    checked,
    disabled,
    label,
    onChange,
}: {
    checked: boolean;
    disabled: boolean;
    label: string;
    onChange: (value: boolean) => void;
}) => (
    <button
        type={'button'}
        role={'switch'}
        aria-checked={checked}
        aria-label={label}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        css={[
            tw`relative inline-flex flex-none items-center h-6 w-11 rounded-full transition-colors duration-150`,
            tw`focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-surface`,
            checked ? tw`bg-primary-500` : tw`bg-gray-600`,
            disabled && tw`opacity-50 cursor-not-allowed`,
        ]}
    >
        <span
            css={[
                tw`inline-block w-5 h-5 bg-white rounded-full shadow transform transition-transform duration-150`,
                checked ? tw`translate-x-5` : tw`translate-x-1`,
            ]}
        />
    </button>
);

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addError, clearFlashes } = useFlash();

    const [addons, setAddons] = useState<Addon[]>([]);
    const [loading, setLoading] = useState(true);
    const [working, setWorking] = useState<string | null>(null);
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState<Category>('All');

    // Version lists are fetched lazily, per addon, the first time the user
    // touches the dropdown. Loading them all up front would mean one upstream
    // API call per card on every page view.
    const [versions, setVersions] = useState<Record<string, AddonVersion[]>>({});
    const [fetchingVersions, setFetchingVersions] = useState<string | null>(null);
    const [selected, setSelected] = useState<Record<string, string>>({});

    const fetchAddons = () => {
        clearFlashes('addons');
        return getAddons(uuid)
            .then((data) => setAddons(data))
            .catch((error) => {
                console.error(error);
                addError({ key: 'addons', message: httpErrorToHuman(error) });
            });
    };

    useEffect(() => {
        fetchAddons().then(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const loadVersions = (addon: Addon) => {
        if (!addon.hasVersions || versions[addon.id] !== undefined || fetchingVersions === addon.id) {
            return;
        }

        setFetchingVersions(addon.id);
        getAddonVersions(uuid, addon.id)
            .then((data) => setVersions((current) => ({ ...current, [addon.id]: data.versions })))
            .catch((error) => {
                console.error(error);
                // Remember the failure so we don't retry on every render.
                setVersions((current) => ({ ...current, [addon.id]: [] }));
                addError({ key: 'addons', message: httpErrorToHuman(error) });
            })
            .then(() => setFetchingVersions(null));
    };

    const install = (addon: Addon) => {
        setWorking(addon.id);
        clearFlashes('addons');
        installAddon(uuid, addon.id, selected[addon.id] || null)
            .then(() => fetchAddons())
            .catch((error) => {
                console.error(error);
                addError({ key: 'addons', message: httpErrorToHuman(error) });
            })
            .then(() => setWorking(null));
    };

    const uninstall = (addon: Addon) => {
        setWorking(addon.id);
        clearFlashes('addons');
        uninstallAddon(uuid, addon.id)
            .then(() => fetchAddons())
            .catch((error) => {
                console.error(error);
                addError({ key: 'addons', message: httpErrorToHuman(error) });
            })
            .then(() => setWorking(null));
    };

    const toggleEnabled = (addon: Addon, enabled: boolean) => {
        setWorking(addon.id);
        clearFlashes('addons');
        setAddonState(uuid, addon.id, enabled)
            .then(() => setAddons((current) => current.map((a) => (a.id === addon.id ? { ...a, enabled } : a))))
            .catch((error) => {
                console.error(error);
                addError({ key: 'addons', message: httpErrorToHuman(error) });
            })
            .then(() => setWorking(null));
    };

    const filtered = useMemo(
        () =>
            addons.filter((addon) => {
                const matchesCategory = category === 'All' || addon.category === category;
                const haystack = `${addon.name} ${addon.author} ${addon.description}`.toLowerCase();
                return matchesCategory && haystack.includes(query.toLowerCase().trim());
            }),
        [addons, query, category]
    );

    const installedCount = addons.filter((a) => a.installed).length;

    return (
        <ServerContentBlock title={'Plugins'}>
            <FlashMessageRender byKey={'addons'} css={tw`mb-4`} />

            <div css={tw`flex flex-wrap items-center justify-between mb-6`}>
                <div css={tw`mb-4 sm:mb-0`}>
                    <h1 css={tw`text-2xl text-gray-100`}>Plugins</h1>
                    <p css={tw`text-sm text-gray-400 mt-1`}>
                        {installedCount > 0
                            ? `${installedCount} installed on this server.`
                            : 'Browse and install plugins for this server in one click.'}
                    </p>
                </div>
                <div css={tw`relative w-full sm:w-72`}>
                    <div css={tw`absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none`}>
                        <FontAwesomeIcon icon={faSearch} />
                    </div>
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={'Search plugins...'}
                        css={tw`pl-10`}
                    />
                </div>
            </div>

            <div css={tw`flex flex-wrap gap-2 mb-6`}>
                {categories.map((c) => (
                    <button
                        key={c}
                        onClick={() => setCategory(c)}
                        css={[
                            tw`px-4 py-1.5 rounded-full text-sm font-medium transition-colors duration-150`,
                            category === c
                                ? tw`bg-primary-500 text-white`
                                : tw`bg-raised text-gray-300 hover:bg-gray-700`,
                        ]}
                    >
                        {c === 'All' ? 'All' : `${c}s`}
                    </button>
                ))}
            </div>

            {loading ? (
                <Spinner size={'large'} centered />
            ) : filtered.length === 0 ? (
                <p css={tw`text-center text-sm text-gray-400 py-16`}>No plugins match your search.</p>
            ) : (
                <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}>
                    {filtered.map((addon) => {
                        const busy = working === addon.id;
                        const dimmed = addon.installed && !addon.enabled;
                        const choice = selected[addon.id] || '';
                        const outdated =
                            addon.installed && choice !== '' && choice !== (addon.installedVersion ?? '');

                        return (
                            <div
                                key={addon.id}
                                css={[
                                    tw`flex flex-col bg-surface border rounded-lg p-4 transition-colors duration-150`,
                                    addon.installed ? tw`border-primary-700` : tw`border-gray-700 hover:border-gray-600`,
                                ]}
                            >
                                <div css={[tw`flex items-start`, dimmed && tw`opacity-60`]}>
                                    <div
                                        css={[
                                            tw`w-11 h-11 flex-none rounded-lg flex items-center justify-center text-white shadow-md`,
                                            badgeColor(addon.category),
                                        ]}
                                    >
                                        <FontAwesomeIcon icon={faPuzzlePiece} />
                                    </div>
                                    <div css={tw`ml-3 min-w-0`}>
                                        <p css={tw`text-gray-100 font-medium leading-tight truncate`}>{addon.name}</p>
                                        <p css={tw`text-xs text-gray-500 truncate`}>by {addon.author}</p>
                                    </div>
                                    <span
                                        css={tw`ml-auto flex-none text-2xs uppercase tracking-wide text-gray-300 bg-canvas rounded-full px-2 py-1`}
                                    >
                                        {addon.category}
                                    </span>
                                </div>

                                <p css={[tw`text-sm text-gray-400 mt-3 flex-1 leading-relaxed`, dimmed && tw`opacity-60`]}>
                                    {addon.description}
                                </p>

                                <div css={tw`flex items-center text-xs text-gray-500 mt-3`}>
                                    <span css={tw`text-warning-500`}>
                                        <FontAwesomeIcon icon={faStar} css={tw`mr-1`} />
                                        {addon.rating.toFixed(1)}
                                    </span>
                                    <span css={tw`ml-4`}>
                                        <FontAwesomeIcon icon={faDownload} css={tw`mr-1`} />
                                        {addon.downloads}
                                    </span>
                                    <span css={tw`ml-auto`}>
                                        {addon.installedVersion
                                            ? `v${addon.installedVersion}`
                                            : addon.hasVersions
                                            ? 'latest'
                                            : `v${addon.version}`}
                                    </span>
                                </div>

                                {addon.hasVersions && (
                                    <div css={tw`mt-3`}>
                                        <label
                                            htmlFor={`version-${addon.id}`}
                                            css={tw`block text-2xs uppercase tracking-wide text-gray-500 mb-1`}
                                        >
                                            Available versions
                                        </label>
                                        <select
                                            id={`version-${addon.id}`}
                                            value={choice}
                                            disabled={busy}
                                            onFocus={() => loadVersions(addon)}
                                            onMouseDown={() => loadVersions(addon)}
                                            onChange={(e) =>
                                                setSelected((current) => ({ ...current, [addon.id]: e.target.value }))
                                            }
                                            css={[
                                                tw`w-full bg-canvas border border-gray-700 rounded-md text-sm text-gray-200 py-2 px-2`,
                                                tw`focus:outline-none focus:ring-2 focus:ring-primary-400`,
                                                busy && tw`opacity-60 cursor-not-allowed`,
                                            ]}
                                        >
                                            <option value={''}>
                                                {fetchingVersions === addon.id
                                                    ? 'Loading versions...'
                                                    : 'Latest version (recommended)'}
                                            </option>
                                            {(versions[addon.id] ?? []).map((version) => (
                                                <option key={version.version} value={version.version}>
                                                    {versionLabel(version)}
                                                </option>
                                            ))}
                                        </select>
                                        {versions[addon.id]?.length === 0 && fetchingVersions !== addon.id && (
                                            <p css={tw`text-2xs text-gray-500 mt-1`}>
                                                Version list unavailable right now — the latest build will be used.
                                            </p>
                                        )}
                                    </div>
                                )}

                                {addon.installed ? (
                                    <div css={tw`mt-4 pt-3 border-t border-gray-700`}>
                                        <div css={tw`flex items-center`}>
                                            <Switch
                                                checked={addon.enabled}
                                                disabled={busy}
                                                label={`${addon.enabled ? 'Disable' : 'Enable'} ${addon.name}`}
                                                onChange={(value) => toggleEnabled(addon, value)}
                                            />
                                            <span css={tw`ml-3 text-sm text-gray-300`}>
                                                {addon.enabled ? 'Enabled' : 'Disabled'}
                                            </span>
                                            <button
                                                disabled={busy}
                                                onClick={() => uninstall(addon)}
                                                title={'Remove this plugin'}
                                                css={[
                                                    tw`ml-auto px-3 py-1.5 rounded-md text-sm font-medium text-gray-300`,
                                                    tw`hover:bg-danger-600 hover:text-white transition-colors duration-150`,
                                                    busy && tw`opacity-60 cursor-not-allowed`,
                                                ]}
                                            >
                                                {busy ? (
                                                    <FontAwesomeIcon icon={faSpinner} spin />
                                                ) : (
                                                    <>
                                                        <FontAwesomeIcon icon={faTrash} css={tw`mr-2`} />
                                                        Remove
                                                    </>
                                                )}
                                            </button>
                                        </div>
                                        {addon.hasVersions && (
                                            <button
                                                disabled={busy}
                                                onClick={() => install(addon)}
                                                css={[
                                                    tw`mt-3 w-full py-2 rounded-md text-sm font-semibold flex items-center justify-center`,
                                                    tw`transition-colors duration-150`,
                                                    outdated
                                                        ? tw`bg-primary-500 text-white hover:bg-primary-600`
                                                        : tw`bg-raised text-gray-300 hover:bg-gray-700`,
                                                    busy && tw`opacity-60 cursor-not-allowed`,
                                                ]}
                                            >
                                                {busy ? (
                                                    <FontAwesomeIcon icon={faSpinner} spin />
                                                ) : (
                                                    <>
                                                        <FontAwesomeIcon icon={faSync} css={tw`mr-2`} />
                                                        {outdated ? `Switch to ${choice}` : 'Reinstall latest'}
                                                    </>
                                                )}
                                            </button>
                                        )}
                                    </div>
                                ) : (
                                    <button
                                        disabled={busy}
                                        onClick={() => install(addon)}
                                        css={[
                                            tw`mt-4 w-full py-2 rounded-md text-sm font-semibold flex items-center justify-center`,
                                            tw`bg-primary-500 text-white hover:bg-primary-600 transition-colors duration-150`,
                                            busy && tw`opacity-60 cursor-not-allowed`,
                                        ]}
                                    >
                                        {busy ? (
                                            <FontAwesomeIcon icon={faSpinner} spin />
                                        ) : (
                                            <>
                                                <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                                                {choice === '' ? 'Install' : `Install ${choice}`}
                                            </>
                                        )}
                                    </button>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </ServerContentBlock>
    );
};
