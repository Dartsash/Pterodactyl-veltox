import React, { useMemo, useState } from 'react';
import tw from 'twin.macro';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Button from '@/components/elements/Button';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import FlashMessageRender from '@/components/FlashMessageRender';
import useFlash from '@/plugins/useFlash';
import updateStartupCommand, {
    allStartupOptions,
    StartupCommandOptions,
    StartupCommandPayload,
    StartupEditorMeta,
} from '@/api/server/updateStartupCommand';

// Kept in sync with StartupCommandBuilderService on the Panel. Only used to
// render a live preview -- the command that actually gets saved is always built
// server side from the same whitelist.
const AIKAR_FLAGS =
    '-XX:+UseG1GC -XX:+ParallelRefProcEnabled -XX:MaxGCPauseMillis=200 -XX:+UnlockExperimentalVMOptions ' +
    '-XX:+DisableExplicitGC -XX:+AlwaysPreTouch -XX:G1NewSizePercent=30 -XX:G1MaxNewSizePercent=40 ' +
    '-XX:G1HeapRegionSize=8M -XX:G1ReservePercent=20 -XX:G1HeapWastePercent=5 -XX:G1MixedGCCountTarget=4 ' +
    '-XX:InitiatingHeapOccupancyPercent=15 -XX:G1MixedGCLiveThresholdPercent=90 ' +
    '-XX:G1RSetUpdatingPauseTimePercent=5 -XX:SurvivorRatio=32 -XX:+PerfDisableSharedMem ' +
    '-XX:MaxTenuringThreshold=1 -Dusing.aikars.flags=https://mcflags.emc.gs -Daikars.new.flags=true';

const DEFAULT_MEMORY_FLAGS = '-Xms128M -XX:MaxRAMPercentage=95.0';

const TOGGLES: Array<{ key: keyof StartupCommandOptions; label: string; hint: string; flag: string }> = [
    {
        key: 'aikar',
        label: 'Optimization flags (Aikar)',
        hint: 'Recommended garbage collector tuning for most Minecraft servers.',
        flag: AIKAR_FLAGS,
    },
    {
        key: 'ignore_java_version',
        label: 'Ignore Java version check',
        hint: 'Lets Paper boot on an unsupported Java version. It only skips the check - if the server crashes, switch the Docker image instead.',
        flag: '-DPaper.IgnoreJavaVersion=true',
    },
    {
        key: 'utf8',
        label: 'UTF-8 encoding',
        hint: 'Fixes broken characters in the console and in config files.',
        flag: '-Dfile.encoding=UTF-8',
    },
    {
        key: 'console_compat',
        label: 'Console compatibility',
        hint: 'Disables JLine and enables ANSI colours for the web console.',
        flag: '-Dterminal.jline=false -Dterminal.ansi=true',
    },
    {
        key: 'nogui',
        label: 'Disable GUI (--nogui)',
        hint: 'Stops the server from opening the built in Java window.',
        flag: '--nogui',
    },
];

interface Props {
    uuid: string;
    rawStartupCommand: string;
    editor: StartupEditorMeta;
    onUpdated: (invocation: string, rawStartupCommand: string, options: StartupCommandOptions) => void;
}

const jarTokenFor = (raw: string): string => {
    const match = /-jar\s+(\S+)/.exec(raw || '');

    return match && /^[A-Za-z0-9_.\-/{}]+$/.test(match[1]) ? match[1] : '{{SERVER_JARFILE}}';
};

const StartupCommandBox = ({ uuid, rawStartupCommand, editor, onUpdated }: Props) => {
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    // The safe builder is always the default, and the only option most users see.
    const [mode, setMode] = useState<'auto' | 'manual'>('auto');
    const [loading, setLoading] = useState(false);
    const [options, setOptions] = useState<StartupCommandOptions>(editor.options);
    const [command, setCommand] = useState(rawStartupCommand);

    // Options an administrator switched off under /admin/addons never show up.
    // If the API did not send a list at all, show everything rather than nothing.
    const available =
        Array.isArray(editor.availableOptions) && editor.availableOptions.length > 0
            ? editor.availableOptions
            : allStartupOptions;
    const allowed = (key: string) => available.includes(key);
    const visibleToggles = TOGGLES.filter((toggle) => allowed(toggle.key));

    const preview = useMemo(() => {
        const on = (key: keyof StartupCommandOptions) => (allowed(key) ? options[key] : null);

        const parts = ['java'];
        const memory = on('memory');

        parts.push(memory ? `-Xms${memory}M -Xmx${memory}M` : DEFAULT_MEMORY_FLAGS);

        if (on('aikar')) parts.push(AIKAR_FLAGS);
        if (on('ignore_java_version')) parts.push('-DPaper.IgnoreJavaVersion=true');
        if (on('utf8')) parts.push('-Dfile.encoding=UTF-8');
        if (on('console_compat')) parts.push('-Dterminal.jline=false -Dterminal.ansi=true');

        parts.push(`-jar ${jarTokenFor(rawStartupCommand)}`);

        if (on('nogui')) parts.push('--nogui');

        return parts.join(' ');
    }, [options, rawStartupCommand, editor.availableOptions]);

    const submit = (payload: StartupCommandPayload) => {
        setLoading(true);
        clearFlashes('startup:command');

        updateStartupCommand(uuid, payload)
            .then((data) => {
                setOptions(data.options);
                setCommand(data.rawStartupCommand);
                onUpdated(data.invocation, data.rawStartupCommand, data.options);

                addFlash({
                    key: 'startup:command',
                    type: 'success',
                    message:
                        payload.mode === 'reset'
                            ? 'Startup command restored to the egg default. It will be used the next time the server starts.'
                            : 'Startup command updated. It will be used the next time the server starts.',
                });
            })
            .catch((error) => {
                console.error(error);
                clearAndAddHttpError({ key: 'startup:command', error });
            })
            .then(() => setLoading(false));
    };

    const save = () => submit(mode === 'auto' ? { mode: 'auto', options } : { mode: 'manual', command });

    const reset = () => {
        if (
            !window.confirm(
                'Reset the startup command to the default defined by this server\u2019s egg? Every custom flag will be removed.'
            )
        ) {
            return;
        }

        setMode('auto');
        submit({ mode: 'reset' });
    };

    return (
        <TitledGreyBox title={'Startup Command Editor'} css={tw`mt-8 relative`}>
            <SpinnerOverlay visible={loading} />
            <div css={tw`px-1 py-2`}>
                <FlashMessageRender byKey={'startup:command'} css={tw`mb-4`} />

                <div css={tw`flex flex-wrap items-center mb-6`}>
                    <Button
                        size={'xsmall'}
                        color={mode === 'auto' ? 'primary' : 'grey'}
                        isSecondary={mode !== 'auto'}
                        onClick={() => setMode('auto')}
                        css={tw`mr-2`}
                    >
                        Automatic (recommended)
                    </Button>
                    {editor.canUseManual && (
                        <Button
                            size={'xsmall'}
                            color={mode === 'manual' ? 'primary' : 'grey'}
                            isSecondary={mode !== 'manual'}
                            onClick={() => setMode('manual')}
                        >
                            Manual
                        </Button>
                    )}
                </div>

                {mode === 'auto' ? (
                    <>
                        {allowed('memory') && (
                        <div css={tw`mb-6`}>
                            <Label>Heap size (MB)</Label>
                            <Input
                                type={'number'}
                                min={128}
                                max={editor.memoryLimit || undefined}
                                placeholder={'Automatic'}
                                value={options.memory ?? ''}
                                onChange={(e) =>
                                    setOptions({
                                        ...options,
                                        memory: e.currentTarget.value === '' ? null : Number(e.currentTarget.value),
                                    })
                                }
                            />
                            <p css={tw`text-xs text-gray-300 mt-2`}>
                                Leave empty to let the server use as much of its allocation as possible.
                                {editor.memoryLimit
                                    ? ` This server is limited to ${editor.memoryLimit} MB, and larger values are reduced automatically.`
                                    : ''}
                            </p>
                        </div>
                        )}

                        <div css={tw`grid gap-4 md:grid-cols-2`}>
                            {visibleToggles.map((toggle) => (
                                <label
                                    key={toggle.key}
                                    css={tw`flex items-start cursor-pointer bg-canvas border border-gray-700 rounded-lg p-3`}
                                >
                                    <Input
                                        type={'checkbox'}
                                        css={tw`mt-1 mr-3`}
                                        checked={Boolean(options[toggle.key])}
                                        onChange={(e) =>
                                            setOptions({ ...options, [toggle.key]: e.currentTarget.checked })
                                        }
                                    />
                                    <span>
                                        <span css={tw`block text-sm`}>{toggle.label}</span>
                                        <span css={tw`block text-xs text-gray-400 mt-1`}>{toggle.hint}</span>
                                    </span>
                                </label>
                            ))}
                        </div>

                        {visibleToggles.length === 0 && !allowed('memory') && (
                            <p css={tw`text-sm text-gray-300`}>
                                An administrator has not enabled any startup options for this panel.
                            </p>
                        )}

                        <div css={tw`mt-6`}>
                            <Label>Preview</Label>
                            <p css={tw`font-mono text-xs bg-canvas border border-gray-700 rounded-lg py-2 px-4 break-all`}>{preview}</p>
                        </div>
                    </>
                ) : (
                    <>
                        <Label>Raw startup command</Label>
                        <textarea
                            css={tw`w-full font-mono text-xs bg-canvas text-gray-100 rounded-lg py-2 px-4 border border-gray-700`}
                            rows={4}
                            value={command}
                            onChange={(e) => setCommand(e.currentTarget.value)}
                        />
                        <p css={tw`text-xs text-warning-400 mt-2`}>
                            Administrator only. The command must start with &quot;java&quot;, contain &quot;-jar&quot;,
                            and may not use shell characters such as ; &amp; | ` $ &lt; &gt;. A requested heap size
                            larger than the server&apos;s memory limit is rejected.
                        </p>
                    </>
                )}

                <div css={tw`mt-6 flex items-center justify-end`}>
                    <Button isSecondary color={'grey'} onClick={reset} disabled={loading} css={tw`mr-2`}>
                        Reset to default
                    </Button>
                    <Button onClick={save} disabled={loading}>
                        Save
                    </Button>
                </div>

                <p css={tw`text-xs text-gray-400 mt-4`}>
                    Changes apply the next time the server is started. &quot;Reset to default&quot; restores the command
                    that came with this server&apos;s egg.
                </p>
            </div>
        </TitledGreyBox>
    );
};

export default StartupCommandBox;
