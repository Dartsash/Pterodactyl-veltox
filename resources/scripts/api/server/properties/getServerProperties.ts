import http from '@/api/http';

export type PropertyType = 'text' | 'number' | 'boolean' | 'select';

export type PropertyValue = string | number | boolean | null;

export interface PropertyField {
    key: string;
    label: string;
    description: string | null;
    group: string;
    type: PropertyType;
    options: string[] | null;
    min: number | null;
    max: number | null;
    // Marks settings that can expose the server if switched off, such as online-mode.
    warning: boolean;
    // True when the value may be something we do not list, e.g. a world type
    // provided by a mod. The picker then keeps the current value as an option.
    allowCustom: boolean;
}

export interface ServerProperties {
    // False when server.properties does not exist yet, e.g. the server never booted.
    available: boolean;
    groups: Record<string, string>;
    fields: PropertyField[];
    values: Record<string, PropertyValue>;
}

const transform = (data: any): ServerProperties => ({
    available: Boolean(data.available),
    groups: data.groups || {},
    fields: ((data.fields || []) as any[]).map(
        (field): PropertyField => ({
            key: field.key,
            label: field.label,
            description: field.description ?? null,
            group: field.group,
            type: field.type,
            options: field.options ?? null,
            min: field.min ?? null,
            max: field.max ?? null,
            warning: Boolean(field.warning),
            allowCustom: Boolean(field.allow_custom),
        })
    ),
    values: (data.values || {}) as Record<string, PropertyValue>,
});

export default async (uuid: string): Promise<ServerProperties> => {
    const { data } = await http.get(`/api/client/servers/${uuid}/properties`);

    return transform(data.data);
};

export const updateServerProperties = async (
    uuid: string,
    values: Record<string, PropertyValue>
): Promise<ServerProperties> => {
    const { data } = await http.put(`/api/client/servers/${uuid}/properties`, { values });

    return transform(data.data);
};
