import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Spinner from '@/components/elements/Spinner';
import FlashMessageRender from '@/components/FlashMessageRender';
import Button from '@/components/elements/Button';
import Input from '@/components/elements/Input';
import Label from '@/components/elements/Label';
import Select from '@/components/elements/Select';
import Switch from '@/components/elements/Switch';
import useFlash from '@/plugins/useFlash';
import getServerProperties, {
    PropertyField,
    PropertyValue,
    ServerProperties,
    updateServerProperties,
} from '@/api/server/properties/getServerProperties';

const FLASH_KEY = 'server:properties';

interface Option {
    value: string;
    label: string;
}

/**
 * Builds the list for a select. When the file holds a value we do not know about
 * (a world type added by a mod, for example) it is kept as an extra option, so
 * opening the page cannot silently swap the generator to the first entry.
 */
const options = (field: PropertyField, value: PropertyValue): Option[] => {
    const known = (field.options || []).map((option) => ({ value: option, label: option }));
    const current = value === null ? '' : String(value);

    if (current === '' || known.some((option) => option.value === current)) {
        return known;
    }

    return [...known, { value: current, label: `${current} (custom)` }];
};

export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const { clearFlashes, clearAndAddHttpError, addFlash } = useFlash();

    const [data, setData] = useState<ServerProperties | null>(null);
    const [values, setValues] = useState<Record<string, PropertyValue>>({});
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        clearFlashes(FLASH_KEY);

        getServerProperties(uuid)
            .then((properties) => {
                setData(properties);
                setValues(properties.values);
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }));
    }, [uuid]);

    const set = (key: string, value: PropertyValue) => setValues((current) => ({ ...current, [key]: value }));

    const save = () => {
        setLoading(true);
        clearFlashes(FLASH_KEY);

        updateServerProperties(uuid, values)
            .then((properties) => {
                setData(properties);
                setValues(properties.values);
                addFlash({
                    key: FLASH_KEY,
                    type: 'success',
                    message: 'Configuration saved. Restart the server for the changes to take effect.',
                });
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }))
            .then(() => setLoading(false));
    };

    const reload = () => {
        setData(null);
        getServerProperties(uuid)
            .then((properties) => {
                setData(properties);
                setValues(properties.values);
            })
            .catch((error) => clearAndAddHttpError({ key: FLASH_KEY, error }));
    };

    const renderField = (field: PropertyField) => {
        const value = values[field.key];

        if (field.type === 'boolean') {
            return (
                <div key={field.key} css={tw`py-3 border-b border-neutral-700 last:border-b-0`}>
                    <Switch
                        name={field.key}
                        label={field.label}
                        description={field.description || undefined}
                        defaultChecked={Boolean(value)}
                        onChange={(e) => set(field.key, e.currentTarget.checked)}
                    />
                    {field.warning && Boolean(value) === false && (
                        <p css={tw`text-xs text-yellow-400 mt-2 ml-16`}>
                            Heads up: leaving this off lets anyone join without a valid account.
                        </p>
                    )}
                </div>
            );
        }

        return (
            <div key={field.key} css={tw`py-3 border-b border-neutral-700 last:border-b-0`}>
                <Label>{field.label}</Label>
                {field.type === 'select' ? (
                    <Select
                        value={value === null ? '' : String(value)}
                        onChange={(e) => set(field.key, e.currentTarget.value)}
                    >
                        <option value={''} disabled>
                            Not set
                        </option>
                        {options(field, value).map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                ) : (
                    <Input
                        type={field.type === 'number' ? 'number' : 'text'}
                        value={value === null ? '' : String(value)}
                        min={field.type === 'number' ? field.min ?? undefined : undefined}
                        max={field.type === 'number' ? field.max ?? undefined : undefined}
                        maxLength={field.type === 'text' ? field.max ?? undefined : undefined}
                        onChange={(e) =>
                            set(
                                field.key,
                                field.type === 'number'
                                    ? e.currentTarget.value === ''
                                        ? null
                                        : Number(e.currentTarget.value)
                                    : e.currentTarget.value
                            )
                        }
                    />
                )}
                {field.description && <p css={tw`text-xs text-neutral-400 mt-1`}>{field.description}</p>}
            </div>
        );
    };

    return (
        <ServerContentBlock title={'Configuration'}>
            <FlashMessageRender byKey={FLASH_KEY} css={tw`mb-4`} />
            {!data ? (
                <Spinner size={'large'} centered />
            ) : !data.available ? (
                <TitledGreyBox title={'Configuration'}>
                    <p css={tw`text-sm text-neutral-300`}>
                        No <code css={tw`text-neutral-100`}>server.properties</code> file was found for this server.
                        Start the server once so it gets generated, then come back to this page.
                    </p>
                    <div css={tw`mt-4`}>
                        <Button isSecondary onClick={reload}>
                            Check again
                        </Button>
                    </div>
                </TitledGreyBox>
            ) : (
                <div css={tw`relative`}>
                    <SpinnerOverlay visible={loading} />
                    <div css={tw`grid gap-4 lg:grid-cols-2`}>
                        {Object.keys(data.groups).map((group) => {
                            const fields = data.fields.filter((field) => field.group === group);

                            if (fields.length === 0) {
                                return null;
                            }

                            return (
                                <TitledGreyBox key={group} title={data.groups[group]}>
                                    {fields.map((field) => renderField(field))}
                                </TitledGreyBox>
                            );
                        })}
                    </div>
                    <div css={tw`flex items-center justify-end mt-6`}>
                        <p css={tw`text-xs text-neutral-400 mr-4`}>
                            Changes are written to server.properties and apply after a restart.
                        </p>
                        <Button onClick={save} disabled={loading}>
                            Save configuration
                        </Button>
                    </div>
                </div>
            )}
        </ServerContentBlock>
    );
};
