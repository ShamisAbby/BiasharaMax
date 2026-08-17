import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import BiCard from '@/Components/Bi/BiCard';
import BiModal from '@/Components/Bi/BiModal';
import { useBiNotification } from '@/Components/Bi/BiNotification';
import Checkbox from '@/Components/Checkbox';
import { useConfirm } from '@/Components/ConfirmDialog';
import SecondaryButton from '@/Components/SecondaryButton';
import SelectInput from '@/Components/SelectInput';
import TextInput from '@/Components/TextInput';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { ArrowTopRightOnSquareIcon } from '@heroicons/react/24/outline';
import { router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

interface PageRow {
    id: string;
    type: string;
    title: string;
    slug: string;
    is_enabled: boolean;
}

interface TemplateRow {
    id: string;
    name: string;
    slug: string;
    business_type_id: string | null;
    business_type_name: string | null;
    description: string | null;
    status: 'draft' | 'published' | 'archived';
    version: string;
    is_default: boolean;
    pages: PageRow[];
    subscription_plans: { id: string; name: string }[];
    versions_count: number;
}

const STATUS_VARIANT = {
    draft: 'neutral',
    published: 'success',
    archived: 'warning',
} as const;

const PAGE_TYPES = [
    'homepage',
    'about',
    'products',
    'categories',
    'services',
    'gallery',
    'testimonials',
    'blog',
    'contact',
    'faq',
    'privacy_policy',
    'terms',
    'booking_form',
];

export default function WebsiteTemplatesIndex({
    templates,
    businessTypes,
}: {
    templates: TemplateRow[];
    businessTypes: { id: string; name: string }[];
    /**
     * Sent by the controller and not read here.
     *
     * `platform.operations.website-templates.plans.update` exists and
     * gates which subscription plans may use a template, but nothing on
     * this screen calls it — so plan assignment is currently only
     * reachable from the Filament panel. Left declared rather than
     * removed so the gap stays visible instead of the prop quietly
     * disappearing from the contract.
     */
    plans?: { id: string; name: string }[];
}) {
    const askConfirm = useConfirm();
    const { notify } = useBiNotification();
    const [editing, setEditing] = useState<TemplateRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [managingPages, setManagingPages] = useState<TemplateRow | null>(
        null,
    );
    const [cloning, setCloning] = useState<TemplateRow | null>(null);
    const [cloneName, setCloneName] = useState('');
    const [newPageType, setNewPageType] = useState('homepage');
    const [newPageTitle, setNewPageTitle] = useState('');

    const { data, setData, post, patch, processing, errors, reset } = useForm({
        name: '',
        slug: '',
        business_type_id: '',
        description: '',
        is_default: false,
    });

    const openCreate = () => {
        reset();
        setCreating(true);
    };

    const openEdit = (template: TemplateRow) => {
        setData({
            name: template.name,
            slug: template.slug,
            business_type_id: template.business_type_id ?? '',
            description: template.description ?? '',
            is_default: template.is_default,
        });
        setEditing(template);
    };

    const close = () => {
        setEditing(null);
        setCreating(false);
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();

        if (editing) {
            patch(
                route(
                    'platform.operations.website-templates.update',
                    editing.id,
                ),
                {
                    onSuccess: () => {
                        close();
                        notify('Template updated.', 'success');
                    },
                },
            );
        } else {
            post(route('platform.operations.website-templates.store'), {
                onSuccess: () => {
                    close();
                    notify('Template created.', 'success');
                },
            });
        }
    };

    const publish = (template: TemplateRow) => {
        router.post(
            route('platform.operations.website-templates.publish', template.id),
            {},
            {
                onSuccess: () => notify('Template published.', 'success'),
            },
        );
    };

    const archive = (template: TemplateRow) => {
        router.post(
            route('platform.operations.website-templates.archive', template.id),
            {},
            {
                onSuccess: () => notify('Template archived.', 'success'),
            },
        );
    };

    const destroy = (template: TemplateRow) => {
        askConfirm({
            title: `Delete "${template.name}"?`,
            message: 'This cannot be undone.',
            tone: 'danger',
            confirmLabel: 'Delete',
            onConfirm: () => {
                router.delete(
                    route(
                        'platform.operations.website-templates.destroy',
                        template.id,
                    ),
                );
            },
        });
    };

    const submitClone = (e: FormEvent) => {
        e.preventDefault();
        if (!cloning) return;

        router.post(
            route('platform.operations.website-templates.clone', cloning.id),
            { name: cloneName },
            {
                onSuccess: () => {
                    setCloning(null);
                    notify('Template cloned.', 'success');
                },
            },
        );
    };

    const addPage = () => {
        if (!managingPages || !newPageTitle) return;

        router.post(
            route(
                'platform.operations.website-templates.pages.store',
                managingPages.id,
            ),
            {
                type: newPageType,
                title: newPageTitle,
                slug: newPageTitle.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
            },
            {
                onSuccess: () => setNewPageTitle(''),
            },
        );
    };

    const togglePage = (template: TemplateRow, page: PageRow) => {
        router.patch(
            route('platform.operations.website-templates.pages.update', [
                template.id,
                page.id,
            ]),
            {
                type: page.type,
                title: page.title,
                slug: page.slug,
                is_enabled: !page.is_enabled,
            },
        );
    };

    const removePage = (template: TemplateRow, page: PageRow) => {
        router.delete(
            route('platform.operations.website-templates.pages.destroy', [
                template.id,
                page.id,
            ]),
        );
    };

    const show = editing !== null || creating;

    return (
        <PlatformLayout>
            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            Website Templates
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            {templates.length} templates.
                        </p>
                    </div>
                    <BiButton onClick={openCreate}>New template</BiButton>
                </div>

                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {templates.map((template) => (
                        <BiCard
                            key={template.id}
                            title={template.name}
                            actions={
                                <div className="flex gap-1.5">
                                    <BiBadge
                                        variant={
                                            STATUS_VARIANT[template.status]
                                        }
                                    >
                                        {template.status}
                                    </BiBadge>
                                    {template.is_default && (
                                        <BiBadge variant="info">
                                            Default
                                        </BiBadge>
                                    )}
                                </div>
                            }
                        >
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                {template.description ?? 'No description'}
                            </p>
                            <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                                {template.business_type_name ??
                                    'Any business type'}{' '}
                                · v{template.version} · {template.pages.length}{' '}
                                pages · {template.versions_count} versions
                            </p>

                            <div className="mt-4 flex flex-wrap gap-3 text-sm">
                                {/*
                                  A real anchor with target="_blank", not an
                                  Inertia <Link>. The preview renders the
                                  public site component, which replaces the
                                  whole admin chrome — opening it in place
                                  would strand an admin outside the panel
                                  they were working in, mid-comparison
                                  between templates.

                                  rel="noopener" because target="_blank"
                                  otherwise hands the opened page a
                                  window.opener reference back to the admin.
                                */}
                                <a
                                    href={route(
                                        'platform.operations.website-templates.preview',
                                        template.id,
                                    )}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="inline-flex items-center gap-1 text-indigo-600 hover:underline"
                                >
                                    View
                                    <ArrowTopRightOnSquareIcon className="h-3.5 w-3.5" />
                                </a>
                                <button
                                    onClick={() => openEdit(template)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Edit
                                </button>
                                <button
                                    onClick={() => setManagingPages(template)}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Pages
                                </button>
                                <button
                                    onClick={() => {
                                        setCloning(template);
                                        setCloneName(`${template.name} copy`);
                                    }}
                                    className="text-indigo-600 hover:underline"
                                >
                                    Clone
                                </button>
                                {template.status !== 'published' && (
                                    <button
                                        onClick={() => publish(template)}
                                        className="text-emerald-600 hover:underline"
                                    >
                                        Publish
                                    </button>
                                )}
                                {template.status !== 'archived' && (
                                    <button
                                        onClick={() => archive(template)}
                                        className="text-amber-600 hover:underline"
                                    >
                                        Archive
                                    </button>
                                )}
                                <button
                                    onClick={() => destroy(template)}
                                    className="text-red-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </div>
                        </BiCard>
                    ))}
                </div>
            </div>

            <BiModal
                show={show}
                onClose={close}
                title={
                    editing ? `Edit ${editing.name}` : 'New website template'
                }
                maxWidth="xl"
                footer={
                    <>
                        <SecondaryButton type="button" onClick={close}>
                            Cancel
                        </SecondaryButton>
                        <BiButton
                            type="submit"
                            form="website-template-form"
                            disabled={processing}
                        >
                            Save
                        </BiButton>
                    </>
                }
            >
                <form
                    id="website-template-form"
                    onSubmit={submit}
                    className="space-y-4"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Name
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                            />
                            {errors.name && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.name}
                                </p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Slug
                            </label>
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.slug}
                                onChange={(e) =>
                                    setData('slug', e.target.value)
                                }
                            />
                            {errors.slug && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.slug}
                                </p>
                            )}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Business type
                        </label>
                        <SelectInput
                            className="mt-1 block w-full"
                            value={data.business_type_id}
                            onChange={(e) =>
                                setData('business_type_id', e.target.value)
                            }
                        >
                            <option value="">Any business type</option>
                            {businessTypes.map((bt) => (
                                <option key={bt.id} value={bt.id}>
                                    {bt.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Description
                        </label>
                        <TextInput
                            className="mt-1 block w-full"
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                        />
                    </div>

                    <label className="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <Checkbox
                            checked={data.is_default}
                            onChange={(e) =>
                                setData('is_default', e.target.checked)
                            }
                        />
                        Default template for this business type
                    </label>
                </form>
            </BiModal>

            <BiModal
                show={managingPages !== null}
                onClose={() => setManagingPages(null)}
                title={`Pages — ${managingPages?.name ?? ''}`}
                maxWidth="xl"
                footer={
                    <SecondaryButton onClick={() => setManagingPages(null)}>
                        Close
                    </SecondaryButton>
                }
            >
                {managingPages && (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            {managingPages.pages.map((page) => (
                                <div
                                    key={page.id}
                                    className="flex items-center justify-between rounded-md border border-gray-200 p-2 text-sm dark:border-gray-700"
                                >
                                    <div>
                                        <p className="font-medium text-gray-900 dark:text-gray-100">
                                            {page.title}
                                        </p>
                                        <p className="text-xs text-gray-500 dark:text-gray-400">
                                            {page.type}
                                        </p>
                                    </div>
                                    <div className="flex gap-3">
                                        <button
                                            onClick={() =>
                                                togglePage(managingPages, page)
                                            }
                                            className="text-indigo-600 hover:underline"
                                        >
                                            {page.is_enabled
                                                ? 'Disable'
                                                : 'Enable'}
                                        </button>
                                        <button
                                            onClick={() =>
                                                removePage(managingPages, page)
                                            }
                                            className="text-red-600 hover:underline"
                                        >
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            ))}
                            {managingPages.pages.length === 0 && (
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    No pages yet.
                                </p>
                            )}
                        </div>

                        <div className="flex gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                            <SelectInput
                                value={newPageType}
                                onChange={(e) => setNewPageType(e.target.value)}
                            >
                                {PAGE_TYPES.map((t) => (
                                    <option key={t} value={t}>
                                        {t.replace(/_/g, ' ')}
                                    </option>
                                ))}
                            </SelectInput>
                            <TextInput
                                value={newPageTitle}
                                onChange={(e) =>
                                    setNewPageTitle(e.target.value)
                                }
                                placeholder="Page title"
                                className="flex-1"
                            />
                            <BiButton type="button" onClick={addPage}>
                                Add
                            </BiButton>
                        </div>
                    </div>
                )}
            </BiModal>

            <BiModal
                show={cloning !== null}
                onClose={() => setCloning(null)}
                title={`Clone ${cloning?.name ?? ''}`}
                footer={
                    <>
                        <SecondaryButton
                            type="button"
                            onClick={() => setCloning(null)}
                        >
                            Cancel
                        </SecondaryButton>
                        <BiButton type="submit" form="clone-template-form">
                            Clone
                        </BiButton>
                    </>
                }
            >
                <form id="clone-template-form" onSubmit={submitClone}>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        New name
                    </label>
                    <TextInput
                        className="mt-1 block w-full"
                        value={cloneName}
                        onChange={(e) => setCloneName(e.target.value)}
                    />
                </form>
            </BiModal>
        </PlatformLayout>
    );
}
