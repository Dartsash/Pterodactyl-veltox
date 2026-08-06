import http from '@/api/http';

export interface StartupCommandOptions {
    memory: number | null;
    aikar: boolean;
    ignore_java_version: boolean;
    utf8: boolean;
    console_compat: boolean;
    nogui: boolean;
}

export interface StartupEditorMeta {
    options: StartupCommandOptions;
    // Option keys the administrator exposes under /admin/addons.
    availableOptions: string[];
    memoryLimit: number | null;
    canUseManual: boolean;
}

export const allStartupOptions = ['memory', 'aikar', 'ignore_java_version', 'utf8', 'console_compat', 'nogui'];

export interface StartupCommandResponse {
    rawStartupCommand: string;
    invocation: string;
    options: StartupCommandOptions;
}

export type StartupCommandPayload =
    | { mode: 'auto'; options: Partial<StartupCommandOptions> }
    | { mode: 'manual'; command: string }
    // Restores the startup command defined by the server's egg.
    | { mode: 'reset' };

export const defaultStartupOptions: StartupCommandOptions = {
    memory: null,
    aikar: false,
    ignore_java_version: false,
    utf8: false,
    console_compat: false,
    nogui: false,
};

export default async (uuid: string, payload: StartupCommandPayload): Promise<StartupCommandResponse> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/startup/command`, payload);

    return {
        rawStartupCommand: data.data.raw_startup_command,
        invocation: data.data.startup_command,
        options: { ...defaultStartupOptions, ...(data.data.options || {}) },
    };
};
