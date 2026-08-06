import useSWR, { ConfigInterface } from 'swr';
import http, { FractalResponseList } from '@/api/http';
import { rawDataToServerEggVariable } from '@/api/transformers';
import { ServerEggVariable } from '@/api/server/types';
import { allStartupOptions, defaultStartupOptions, StartupEditorMeta } from '@/api/server/updateStartupCommand';

interface Response {
    invocation: string;
    variables: ServerEggVariable[];
    dockerImages: Record<string, string>;
    rawStartupCommand?: string;
    editor?: StartupEditorMeta;
}

export default (uuid: string, initialData?: Response | null, config?: ConfigInterface<Response>) =>
    useSWR(
        [uuid, '/startup'],
        async (): Promise<Response> => {
            const { data } = await http.get(`/api/client/servers/${uuid}/startup`);

            const variables = ((data as FractalResponseList).data || []).map(rawDataToServerEggVariable);
            const editorMeta = data.meta.startup_editor;

            return {
                variables,
                invocation: data.meta.startup_command,
                dockerImages: data.meta.docker_images || {},
                rawStartupCommand: data.meta.raw_startup_command || '',
                editor: editorMeta
                    ? {
                          options: { ...defaultStartupOptions, ...(editorMeta.options || {}) },
                          // Falling back to every option keeps the editor usable even if the
                          // API response predates the per-option admin settings.
                          availableOptions:
                              Array.isArray(editorMeta.available_options) && editorMeta.available_options.length > 0
                                  ? editorMeta.available_options
                                  : allStartupOptions,
                          memoryLimit: editorMeta.memory_limit ?? null,
                          canUseManual: Boolean(editorMeta.can_use_manual),
                      }
                    : undefined,
            };
        },
        { initialData: initialData || undefined, errorRetryCount: 3, ...(config || {}) }
    );
