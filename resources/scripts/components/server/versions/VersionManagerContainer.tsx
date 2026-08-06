import React, { useEffect, useMemo, useState } from 'react';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faBoxOpen,
    faCube,
    faCubes,
    faDownload,
    faExclamationTriangle,
    faNetworkWired,
    faSearch,
    faSpinner,
    faSync,
} from '@fortawesome/free-solid-svg-icons';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Input from '@/components/elements/Input';
import Select from '@/components/elements/Select';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import ConfirmationModal from '@/components/elements/ConfirmationModal';
import { ServerContext } from '@/state/server';
import useFlash from '@/plugins/useFlash';
import { httpErrorToHuman } from '@/api/http';
import getVersions, {
    getCoreBuilds,
    getCoreVersions,
    installVersion,
    ServerCore,
    VersionManagerData,
} from '@/api/server/versions/getVersions';

const FLASH_KEY = 'server:versions';

const categoryIcon = (category: string) => {
    switch (category) {
        case 'modded':
            return faCubes;
        case 'proxy':
            return faNetworkWired;
        default:
            return faCube;
    }
};

const categoryColor = (category: string) => {
    switch (category) {
        case 'modded':
            return tw`bg-cyan-500`;
        case 'proxy':
            return tw`bg-success-500`;
        default:
            return tw`bg-primary-500`;
    }
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { addError, addFlash, clearFlashes } = useFlash();

    const [data, setData] = useState<VersionManagerData | null>(null);
    const [loading, setLoading] = useState(true);
    const [query, setQuery] = useState('');
    const [category, setCategory] = useState('all');

    const [selected, setSelected] = useState<ServerCore | null>(null);
    const [versions, setVersions] = useState<string[]>([]);
    const [builds, setBuilds] = useState<string[]>([]);
    const [version, setVersion] = useState('');
    const [build, setBuild] = useState('');
    const [loadingVersions, setLoadingVersions] = useState(false);
    const [loadingBuilds, setLoadingBuilds] = useState(false);
    const [installing, setInstalling] = useState(false);
    const [confirming, setConfirming] = useState(false);
    // When enabled the whole server root is emptied before the jar is pulled.
    const [wipe, setWipe] = useState(false);

    useEffect(() => {
        clearFlashes(FLASH_KEY);

        getVersions(uuid)
            .then(setData)
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => setLoading(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const loadVersions = (core: ServerCore) => {
        setLoadingVersions(true);
        setVersions([]);
        setBuilds([]);
        setVersion('');
        setBuild('');
        clearFlashes(FLASH_KEY);

        getCoreVersions(uuid, core.key)
            .then((list) => {
                setVersions(list);
                setVersion(list[0] || '');
            })
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => setLoadingVersions(false));
    };

    const selectCore = (core: ServerCore) => {
        if (selected?.key === core.key) {
            setSelected(null);
            return;
        }

        setSelected(core);
        loadVersions(core);
    };

    useEffect(() => {
        if (!selected || !version || !selected.hasBuilds) {
            setBuilds([]);
            setBuild('');
            return;
        }

        setLoadingBuilds(true);
        getCoreBuilds(uuid, selected.key, version)
            .then((list) => {
                setBuilds(list);
                setBuild(list[0] || '');
            })
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => setLoadingBuilds(false));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selected?.key, version]);

    const install = () => {
        if (!selected || !version) {
            return;
        }

        setConfirming(false);
        setInstalling(true);
        clearFlashes(FLASH_KEY);

        installVersion(uuid, selected.key, version, build || null, wipe)
            .then((result) => {
                const wiped = result.wiped > 0 ? `All ${result.wiped} files and folders were deleted first. ` : '';
                // The source is shown so a download that silently fails can be
                // opened by hand to see what the upstream site answers.
                const source = result.url ? ` Source: ${result.url}` : '';

                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    message: result.installer
                        ? `${wiped}${result.label} was downloaded as ${result.filename}. Run it once from the console to finish the installation.${source}`
                        : `${wiped}${result.label} is downloading to ${result.filename}. Start the server once it finishes.${source}`,
                });
            })
            .catch((error) => {
                console.error(error);
                addError({ key: FLASH_KEY, message: httpErrorToHuman(error) });
            })
            .then(() => setInstalling(false));
    };

    const tabs = useMemo(() => {
        if (!data) {
            return [] as Array<{ key: string; label: string }>;
        }

        return [
            { key: 'all', label: 'All' },
            ...Object.keys(data.categories)
                .filter((key) => data.cores.some((core) => core.category === key))
                .map((key) => ({ key, label: data.categories[key] })),
        ];
    }, [data]);

    const filtered = useMemo(() => {
        if (!data) {
            return [] as ServerCore[];
        }

        return data.cores.filter((core) => {
            const matchesCategory = category === 'all' || core.category === category;
            const haystack = `${core.name} ${core.description}`.toLowerCase();

            return matchesCategory && haystack.includes(query.toLowerCase().trim());
        });
    }, [data, query, category]);

    return (
        <ServerContentBlock title={'Versions'}>
            <FlashMessageRender byKey={FLASH_KEY} css={tw`mb-4`} />

            <ConfirmationModal
                visible={confirming}
                title={'Install this version?'}
                buttonText={wipe ? 'Yes, wipe and install' : 'Yes, install it'}
                showSpinnerOverlay={installing}
                onConfirmed={install}
                onModalDismissed={() => setConfirming(false)}
            >
                {selected?.installer ? (
                    <>
                        The installer will be downloaded as <code>{selected.key}-installer.jar</code>. Your current
                        server jar is left untouched, but the installer has to be run once from the console.
                    </>
                ) : (
                    <>
                        This overwrites <code>{data?.jarFile}</code> in the root of your server. Stop the server first
                        and take a backup if the current jar matters. Worlds and plugins are not touched.
                    </>
                )}

                <label css={tw`mt-4 flex items-start cursor-pointer`}>
                    <Input
                        type={'checkbox'}
                        checked={wipe}
                        onChange={(e) => setWipe(e.currentTarget.checked)}
                        css={tw`mt-1 mr-3`}
                    />
                    <span>
                        <span css={[tw`block text-sm font-medium`, wipe ? tw`text-red-400` : tw`text-neutral-200`]}>
                            Delete all server files first
                        </span>
                        <span css={tw`block text-xs text-neutral-400 mt-1`}>
                            Everything in the server root is removed before the new version is downloaded, including
                            worlds, plugins, mods and configs. This cannot be undone.
                        </span>
                    </span>
                </label>

                {wipe && (
                    <div css={tw`mt-4 flex items-start text-sm text-red-400`}>
                        <FontAwesomeIcon icon={faExclamationTriangle} css={tw`mt-0.5 mr-2`} />
                        <span>Stop the server before wiping it, and make a backup if you want to keep anything.</span>
                    </div>
                )}
            </ConfirmationModal>

            <div css={tw`flex flex-wrap items-center justify-between mb-6`}>
                <div css={tw`mb-4 sm:mb-0`}>
                    <h1 css={tw`text-2xl text-gray-100`}>Versions</h1>
                    <p css={tw`text-sm text-gray-400 mt-1`}>
                        {data
                            ? `${data.cores.length} server cores available. Pick a core, choose a version and install it in one click.`
                            : 'Loading available server cores...'}
                    </p>
                </div>
                <div css={tw`relative w-full sm:w-72`}>
                    <div css={tw`absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none`}>
                        <FontAwesomeIcon icon={faSearch} />
                    </div>
                    <Input
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder={'Search cores...'}
                        css={tw`pl-10`}
                    />
                </div>
            </div>

            <div css={tw`flex flex-wrap gap-2 mb-6`}>
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => setCategory(tab.key)}
                        css={[
                            tw`px-4 py-1.5 rounded-full text-sm font-medium transition-colors duration-150`,
                            category === tab.key
                                ? tw`bg-primary-500 text-white`
                                : tw`bg-raised text-gray-300 hover:bg-gray-700`,
                        ]}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {loading ? (
                <Spinner size={'large'} centered />
            ) : filtered.length === 0 ? (
                <p css={tw`text-center text-sm text-gray-400 py-16`}>No cores match your search.</p>
            ) : (
                <div css={tw`grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4`}>
                    {filtered.map((core) => {
                        const active = selected?.key === core.key;

                        return (
                            <div
                                key={core.key}
                                css={[
                                    tw`flex flex-col bg-surface border rounded-lg p-4 transition-colors duration-150`,
                                    active ? tw`border-primary-500` : tw`border-gray-700 hover:border-gray-600`,
                                ]}
                            >
                                <button
                                    type={'button'}
                                    onClick={() => selectCore(core)}
                                    css={tw`flex items-start text-left focus:outline-none`}
                                >
                                    <div
                                        css={[
                                            tw`w-11 h-11 flex-none rounded-lg flex items-center justify-center text-white shadow-md`,
                                            categoryColor(core.category),
                                        ]}
                                    >
                                        <FontAwesomeIcon icon={categoryIcon(core.category)} />
                                    </div>
                                    <div css={tw`ml-3 min-w-0`}>
                                        <p css={tw`text-gray-100 font-medium leading-tight truncate`}>{core.name}</p>
                                        <p css={tw`text-xs text-gray-500 truncate`}>{core.categoryLabel}</p>
                                    </div>
                                    {core.installer && (
                                        <span
                                            css={tw`ml-auto flex-none text-2xs uppercase tracking-wide text-gray-300 bg-canvas rounded-full px-2 py-1`}
                                        >
                                            Installer
                                        </span>
                                    )}
                                </button>

                                <p css={tw`text-sm text-gray-400 mt-3 flex-1 leading-relaxed`}>{core.description}</p>

                                {!active ? (
                                    <button
                                        onClick={() => selectCore(core)}
                                        css={[
                                            tw`mt-4 w-full py-2 rounded-md text-sm font-semibold flex items-center justify-center`,
                                            tw`bg-raised text-gray-200 hover:bg-gray-700 transition-colors duration-150`,
                                        ]}
                                    >
                                        <FontAwesomeIcon icon={faBoxOpen} css={tw`mr-2`} />
                                        Choose version
                                    </button>
                                ) : (
                                    <div css={tw`mt-4 pt-4 border-t border-gray-700`}>
                                        {loadingVersions ? (
                                            <div css={tw`py-4`}>
                                                <Spinner size={'small'} centered />
                                            </div>
                                        ) : versions.length === 0 ? (
                                            <div css={tw`text-sm text-gray-400`}>
                                                <p css={tw`mb-3`}>
                                                    <FontAwesomeIcon
                                                        icon={faExclamationTriangle}
                                                        css={tw`mr-2 text-warning-500`}
                                                    />
                                                    No versions could be loaded.
                                                </p>
                                                <button
                                                    onClick={() => loadVersions(core)}
                                                    css={tw`text-primary-400 hover:text-primary-300 text-sm`}
                                                >
                                                    <FontAwesomeIcon icon={faSync} css={tw`mr-2`} />
                                                    Try again
                                                </button>
                                            </div>
                                        ) : (
                                            <>
                                                <div css={tw`flex gap-3`}>
                                                    <div css={tw`flex-1 min-w-0`}>
                                                        <p
                                                            css={tw`text-2xs uppercase tracking-wide text-gray-400 mb-1`}
                                                        >
                                                            Version
                                                        </p>
                                                        <Select
                                                            value={version}
                                                            onChange={(e) => setVersion(e.currentTarget.value)}
                                                        >
                                                            {versions.map((item) => (
                                                                <option key={item} value={item}>
                                                                    {item}
                                                                </option>
                                                            ))}
                                                        </Select>
                                                    </div>
                                                    {core.hasBuilds && (
                                                        <div css={tw`flex-1 min-w-0`}>
                                                            <p
                                                                css={tw`text-2xs uppercase tracking-wide text-gray-400 mb-1`}
                                                            >
                                                                {core.key === 'fabric' || core.key === 'quilt'
                                                                    ? 'Loader'
                                                                    : 'Build'}
                                                            </p>
                                                            {loadingBuilds ? (
                                                                <div css={tw`py-2`}>
                                                                    <Spinner size={'small'} />
                                                                </div>
                                                            ) : builds.length === 0 ? (
                                                                <p css={tw`text-xs text-gray-500 py-3`}>None</p>
                                                            ) : (
                                                                <Select
                                                                    value={build}
                                                                    onChange={(e) => setBuild(e.currentTarget.value)}
                                                                >
                                                                    {builds.map((item) => (
                                                                        <option key={item} value={item}>
                                                                            {item}
                                                                        </option>
                                                                    ))}
                                                                </Select>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>

                                                <p css={tw`text-xs text-gray-500 mt-3`}>
                                                    {core.installer ? (
                                                        <>Downloads {core.key}-installer.jar, run it once.</>
                                                    ) : (
                                                        <>Overwrites {data?.jarFile}. Stop the server first.</>
                                                    )}
                                                </p>

                                                <button
                                                    disabled={!version || installing}
                                                    onClick={() => setConfirming(true)}
                                                    css={[
                                                        tw`mt-4 w-full py-2 rounded-md text-sm font-semibold flex items-center justify-center`,
                                                        tw`bg-primary-500 text-white hover:bg-primary-600 transition-colors duration-150`,
                                                        (!version || installing) && tw`opacity-60 cursor-not-allowed`,
                                                    ]}
                                                >
                                                    {installing ? (
                                                        <FontAwesomeIcon icon={faSpinner} spin />
                                                    ) : (
                                                        <>
                                                            <FontAwesomeIcon icon={faDownload} css={tw`mr-2`} />
                                                            Install
                                                        </>
                                                    )}
                                                </button>
                                            </>
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
        </ServerContentBlock>
    );
};
