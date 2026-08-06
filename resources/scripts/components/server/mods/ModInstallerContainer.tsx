import React, { useCallback, useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCubes,
    faDownload,
    faExclamationTriangle,
    faExternalLinkAlt,
    faSearch,
    faTrashAlt,
} from '@fortawesome/free-solid-svg-icons';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import ConfirmationModal from '@/components/elements/ConfirmationModal';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import Spinner from '@/components/elements/Spinner';
import useFlash from '@/plugins/useFlash';
import getMods, {
    deleteMod,
    getModVersions,
    installMod,
    InstalledMod,
    ModResult,
    ModsOverview,
    ModVersion,
    searchMods,
    setModState,
} from '@/api/server/mods/getMods';

const FLASH_KEY = 'server:mods';

const SORT_LABELS: Record<string, string> = {
    relevance: 'Best match',
    downloads: 'Most downloaded',
    follows: 'Most followed',
    newest: 'Newest projects',
    updated: 'Recently updated',
};

const size = (bytes: number): string => {
    if (bytes <= 0) {
        return 'unknown size';
    }

    const mb = bytes / 1024 / 1024;

    return mb >= 1 ? mb.toFixed(1) + ' MiB' : Math.max(1, Math.round(bytes / 1024)) + ' KiB';
};

const downloadCount = (value: number): string => {
    if (value >= 1000000) {
        return (value / 1000000).toFixed(1) + 'M';
    }

    if (value >= 1000) {
        return Math.round(value / 1000) + 'K';
    }

    return String(value);
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    const [overview, setOverview] = useState<ModsOverview | null>(null);
    const [installed, setInstalled] = useState<InstalledMod[]>([]);
    const [loading, setLoading] = useState(true);

    const [query, setQuery] = useState('');
    const [loader, setLoader] = useState('');
    const [gameVersion, setGameVersion] = useState('');
    const [sort, setSort] = useState('relevance');

    const [results, setResults] = useState<ModResult[]>([]);
    const [searching, setSearching] = useState(false);
    const [searched, setSearched] = useState(false);

    const [expanded, setExpanded] = useState<string | null>(null);
    const [versions, setVersions] = useState<ModVersion[]>([]);
    const [loadingVersions, setLoadingVersions] = useState(false);
    const [dependencies, setDependencies] = useState(true);

    const [installing, setInstalling] = useState<string | null>(null);
    const [working, setWorking] = useState<string | null>(null);
    const [removing, setRemoving] = useState<InstalledMod | null>(null);

    const filters = useMemo(
        () => ({ loader: loader || null, gameVersion: gameVersion || null }),
        [loader, gameVersion]
    );

    const run = useCallback(
        (searchQuery: string, currentLoader: string, currentVersion: string, currentSort: string) => {
            setSearching(true);
            clearFlashes(FLASH_KEY);

            searchMods(uuid, {
                query: searchQuery,
                loader: currentLoader || null,
                gameVersion: currentVersion || null,
                sort: currentSort,
            })
                .then((data) => {
                    setResults(data);
                    setSearched(true);
                })
                .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
                .then(() => setSearching(false));
        },
        [uuid]
    );

    useEffect(() => {
        getMods(uuid)
            .then((data) => {
                setOverview(data);
                setInstalled(data.installed);

                const detectedLoader = data.detected.loader || '';
                const detectedVersion = data.detected.gameVersion || '';

                setLoader(detectedLoader);
                setGameVersion(detectedVersion);
                setSort('downloads');

                run('', detectedLoader, detectedVersion, 'downloads');
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setLoading(false));
    }, [uuid]);

    const openVersions = (slug: string) => {
        if (expanded === slug) {
            setExpanded(null);

            return;
        }

        setExpanded(slug);
        setVersions([]);
        setLoadingVersions(true);

        getModVersions(uuid, slug, filters)
            .then(setVersions)
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setLoadingVersions(false));
    };

    const install = (mod: ModResult, version?: ModVersion) => {
        setInstalling(version ? mod.slug + ':' + version.id : mod.slug);
        clearFlashes(FLASH_KEY);

        installMod(uuid, mod.slug, {
            version: version?.id ?? null,
            loader: filters.loader,
            gameVersion: filters.gameVersion,
            dependencies,
        })
            .then((result) => {
                setInstalled(result.files);

                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    title: 'Installed',
                    message:
                        mod.title +
                        ' was downloaded to ' +
                        (overview?.directory || '/mods') +
                        '. Restart the server to load it.',
                });

                if (result.unresolvedDependencies.length > 0) {
                    addFlash({
                        key: FLASH_KEY,
                        type: 'warning',
                        title: 'Missing dependencies',
                        message:
                            mod.title +
                            ' requires ' +
                            result.unresolvedDependencies.length +
                            ' dependency(ies) with no build for this loader or game version. Install them manually or it may fail to load.',
                    });
                }
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setInstalling(null));
    };

    const toggle = (mod: InstalledMod) => {
        setWorking(mod.name);
        clearFlashes(FLASH_KEY);

        setModState(uuid, mod.name, !mod.enabled)
            .then(setInstalled)
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setWorking(null));
    };

    const confirmRemoval = () => {
        if (!removing) {
            return;
        }

        const target = removing;

        setRemoving(null);
        setWorking(target.name);
        clearFlashes(FLASH_KEY);

        deleteMod(uuid, target.name)
            .then(() => setInstalled((current) => current.filter((entry) => entry.name !== target.name)))
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setWorking(null));
    };

    return (
        <ServerContentBlock title={'Mods'}>
            <FlashMessageRender byKey={FLASH_KEY} css={tw`mb-4`} />

            {loading ? (
                <div css={tw`w-full flex justify-center py-8`}>
                    <Spinner size={'large'} />
                </div>
            ) : (
                <>
                    {!overview?.detected.loader && (
                        <div css={tw`rounded bg-yellow-500 bg-opacity-25 border border-yellow-600 p-4 mb-4 text-sm`}>
                            <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mr-2`} />
                            Neither this server's egg nor its files told us which mod loader it runs. Pick the
                            loader you actually run below, otherwise you will be offered jars that cannot load. If
                            this is a vanilla, Paper or Spigot server, mods will not load at all until a loader is
                            installed.
                        </div>
                    )}

                    <TitledGreyBox title={'Find mods on Modrinth'} css={tw`mb-6`}>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                run(query, loader, gameVersion, sort);
                            }}
                        >
                            <div css={tw`flex flex-wrap -mx-2`}>
                                <div css={tw`w-full md:w-2/5 px-2 mb-3`}>
                                    <Label>Search</Label>
                                    <Input
                                        value={query}
                                        placeholder={'e.g. create, sodium, journeymap'}
                                        onChange={(e) => setQuery(e.currentTarget.value)}
                                    />
                                </div>
                                <div css={tw`w-1/2 md:w-1/5 px-2 mb-3`}>
                                    <Label>Loader</Label>
                                    <Select value={loader} onChange={(e) => setLoader(e.currentTarget.value)}>
                                        <option value={''}>Any loader</option>
                                        {(overview?.loaders || []).map((entry) => (
                                            <option key={entry} value={entry}>
                                                {entry}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div css={tw`w-1/2 md:w-1/5 px-2 mb-3`}>
                                    <Label>Game version</Label>
                                    <Select value={gameVersion} onChange={(e) => setGameVersion(e.currentTarget.value)}>
                                        <option value={''}>Any version</option>
                                        {(overview?.gameVersions || []).map((entry) => (
                                            <option key={entry} value={entry}>
                                                {entry}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div css={tw`w-full md:w-1/5 px-2 mb-3`}>
                                    <Label>Sort by</Label>
                                    <Select value={sort} onChange={(e) => setSort(e.currentTarget.value)}>
                                        {(overview?.sorts || []).map((entry) => (
                                            <option key={entry} value={entry}>
                                                {SORT_LABELS[entry] || entry}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </div>
                            <div css={tw`flex items-center justify-between mt-1`}>
                                <p css={tw`text-xs text-neutral-400`}>
                                    {overview?.detected.loader
                                        ? 'Detected ' +
                                          overview.detected.loader +
                                          (overview.detected.gameVersion
                                              ? ' on ' + overview.detected.gameVersion
                                              : '') +
                                          (overview.detected.source === 'files'
                                              ? ' from the server files.'
                                              : ' from this server egg.')
                                        : 'Filters apply to both the search and the version list.'}
                                </p>
                                <Button type={'submit'} isLoading={searching}>
                                    <FontAwesomeIcon icon={faSearch} css={tw`mr-2`} />
                                    Search
                                </Button>
                            </div>
                        </form>
                    </TitledGreyBox>

                    <div css={tw`flex flex-wrap -mx-4`}>
                        <div css={tw`w-full lg:w-2/3 px-4`}>
                            {searching && results.length === 0 ? (
                                <div css={tw`w-full flex justify-center py-8`}>
                                    <Spinner />
                                </div>
                            ) : results.length === 0 ? (
                                <p css={tw`text-sm text-neutral-400 py-4`}>
                                    {searched
                                        ? 'Nothing matched those filters. Try a different loader or game version.'
                                        : 'Search Modrinth to get started.'}
                                </p>
                            ) : (
                                results.map((mod) => (
                                    <div
                                        key={mod.slug}
                                        css={tw`bg-neutral-700 rounded p-4 mb-3 border border-transparent hover:border-neutral-600`}
                                    >
                                        <div css={tw`flex items-start`}>
                                            {mod.icon ? (
                                                <img
                                                    src={mod.icon}
                                                    alt={''}
                                                    css={tw`w-12 h-12 rounded mr-4 flex-shrink-0 bg-neutral-800`}
                                                />
                                            ) : (
                                                <div
                                                    css={tw`w-12 h-12 rounded mr-4 flex-shrink-0 bg-neutral-800 flex items-center justify-center text-neutral-500`}
                                                >
                                                    <FontAwesomeIcon icon={faCubes} />
                                                </div>
                                            )}
                                            <div css={tw`flex-1 min-w-0`}>
                                                <div css={tw`flex items-center flex-wrap`}>
                                                    <p css={tw`font-medium mr-2`}>{mod.title}</p>
                                                    {mod.author !== '' && (
                                                        <span css={tw`text-xs text-neutral-400 mr-2`}>
                                                            by {mod.author}
                                                        </span>
                                                    )}
                                                    <span css={tw`text-xs text-neutral-400`}>
                                                        {downloadCount(mod.downloads)} downloads
                                                    </span>
                                                </div>
                                                <p css={tw`text-sm text-neutral-300 mt-1`}>{mod.description}</p>
                                                <div css={tw`flex items-center flex-wrap mt-2`}>
                                                    {mod.loaders.map((entry) => (
                                                        <span
                                                            key={entry}
                                                            css={tw`text-xs bg-neutral-800 rounded px-2 py-1 mr-2 mb-1`}
                                                        >
                                                            {entry}
                                                        </span>
                                                    ))}
                                                    {mod.serverSide === 'unsupported' && (
                                                        <span
                                                            css={tw`text-xs text-yellow-400 bg-yellow-500 bg-opacity-25 rounded px-2 py-1 mr-2 mb-1`}
                                                        >
                                                            client side only
                                                        </span>
                                                    )}
                                                    <a
                                                        href={mod.url}
                                                        target={'_blank'}
                                                        rel={'noreferrer noopener'}
                                                        css={tw`text-xs text-primary-400 mb-1`}
                                                    >
                                                        Modrinth page
                                                        <FontAwesomeIcon icon={faExternalLinkAlt} css={tw`ml-1`} />
                                                    </a>
                                                </div>
                                            </div>
                                            <div css={tw`ml-4 flex-shrink-0 text-right`}>
                                                <Button
                                                    size={'xsmall'}
                                                    isLoading={installing === mod.slug}
                                                    onClick={() => install(mod)}
                                                >
                                                    <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                                                    Install latest
                                                </Button>
                                                <Button
                                                    size={'xsmall'}
                                                    isSecondary
                                                    css={tw`mt-2`}
                                                    onClick={() => openVersions(mod.slug)}
                                                >
                                                    {expanded === mod.slug ? 'Hide versions' : 'All versions'}
                                                </Button>
                                            </div>
                                        </div>

                                        {expanded === mod.slug && (
                                            <div css={tw`mt-4 pt-3 border-t border-neutral-600`}>
                                                {loadingVersions ? (
                                                    <div css={tw`flex justify-center py-4`}>
                                                        <Spinner size={'small'} />
                                                    </div>
                                                ) : versions.length === 0 ? (
                                                    <p css={tw`text-sm text-neutral-400`}>
                                                        No build matches the selected loader and game version.
                                                    </p>
                                                ) : (
                                                    versions.slice(0, 15).map((version) => (
                                                        <div
                                                            key={version.id}
                                                            css={tw`flex items-center justify-between py-2 border-b border-neutral-600 last:border-b-0`}
                                                        >
                                                            <div css={tw`min-w-0`}>
                                                                <p css={tw`text-sm`}>
                                                                    {version.number}
                                                                    {version.type !== 'release' && (
                                                                        <span css={tw`text-xs text-yellow-400 ml-2`}>
                                                                            {version.type}
                                                                        </span>
                                                                    )}
                                                                </p>
                                                                <p css={tw`text-xs text-neutral-400`}>
                                                                    {version.gameVersions.slice(0, 4).join(', ')}
                                                                    {' | '}
                                                                    {size(version.size)}
                                                                    {version.requiredDependencies > 0 &&
                                                                        ' | ' +
                                                                            version.requiredDependencies +
                                                                            ' required dependency(ies)'}
                                                                </p>
                                                            </div>
                                                            <Button
                                                                size={'xsmall'}
                                                                isSecondary
                                                                isLoading={installing === mod.slug + ':' + version.id}
                                                                onClick={() => install(mod, version)}
                                                            >
                                                                Install
                                                            </Button>
                                                        </div>
                                                    ))
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>

                        <div css={tw`w-full lg:w-1/3 px-4`}>
                            <TitledGreyBox title={'Installed mods (' + installed.length + ')'}>
                                <label css={tw`flex items-center text-xs text-neutral-300 mb-3`}>
                                    <input
                                        type={'checkbox'}
                                        checked={dependencies}
                                        onChange={(e) => setDependencies(e.currentTarget.checked)}
                                        css={tw`mr-2`}
                                    />
                                    Also install required dependencies
                                </label>

                                {installed.length === 0 ? (
                                    <p css={tw`text-sm text-neutral-400`}>
                                        Nothing in {overview?.directory || '/mods'} yet.
                                    </p>
                                ) : (
                                    installed.map((mod) => (
                                        <div
                                            key={mod.name}
                                            css={tw`flex items-center justify-between py-2 border-b border-neutral-600 last:border-b-0`}
                                        >
                                            <div css={tw`min-w-0 mr-2`}>
                                                <p
                                                    css={[
                                                        tw`text-sm truncate`,
                                                        !mod.enabled && tw`text-neutral-500 line-through`,
                                                    ]}
                                                >
                                                    {mod.displayName}
                                                </p>
                                                <p css={tw`text-xs text-neutral-400`}>
                                                    {size(mod.size)}
                                                    {!mod.enabled && ' | disabled'}
                                                </p>
                                            </div>
                                            <div css={tw`flex-shrink-0`}>
                                                <Button
                                                    size={'xsmall'}
                                                    isSecondary
                                                    disabled={working === mod.name}
                                                    onClick={() => toggle(mod)}
                                                >
                                                    {mod.enabled ? 'Disable' : 'Enable'}
                                                </Button>
                                                <Button
                                                    size={'xsmall'}
                                                    color={'red'}
                                                    isSecondary
                                                    css={tw`ml-2`}
                                                    disabled={working === mod.name}
                                                    onClick={() => setRemoving(mod)}
                                                >
                                                    <FontAwesomeIcon icon={faTrashAlt} />
                                                </Button>
                                            </div>
                                        </div>
                                    ))
                                )}

                                <p css={tw`text-xs text-neutral-400 mt-4`}>
                                    Mods are loaded when the server starts, so restart it after changing anything here.
                                    Disabling renames the jar to .disabled instead of deleting it.
                                </p>
                            </TitledGreyBox>
                        </div>
                    </div>
                </>
            )}

            <ConfirmationModal
                visible={removing !== null}
                title={'Delete this mod?'}
                buttonText={'Yes, delete it'}
                onConfirmed={confirmRemoval}
                onModalDismissed={() => setRemoving(null)}
            >
                {removing?.displayName} will be removed from {overview?.directory || '/mods'}. Worlds that already use
                its blocks or items may fail to load afterwards.
            </ConfirmationModal>

            <SpinnerOverlay visible={installing !== null} size={'large'} />
        </ServerContentBlock>
    );
};
