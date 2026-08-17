import BiBadge from '@/Components/Bi/BiBadge';
import BiButton from '@/Components/Bi/BiButton';
import PlatformLayout from '@/Layouts/PlatformLayout';
import { ArrowLeftIcon } from '@heroicons/react/24/outline';
import { Link, router } from '@inertiajs/react';

interface ModuleRow {
    id: string;
    slug: string;
    name: string;
    description: string | null;
    /** The effective answer after all four layers. */
    enabled: boolean;
    /** null = no override, so it follows the type and plan. */
    override: boolean | null;
    reason: string;
}

export default function BusinessModules({
    business,
    modules,
}: {
    business: {
        id: string;
        name: string;
        plan: string | null;
        business_type: string | null;
    };
    modules: ModuleRow[];
}) {
    const set = (module: ModuleRow, enabled: boolean | null) => {
        router.patch(
            route('platform.businesses.modules.update', business.id),
            { module_id: module.id, enabled },
            { preserveScroll: true },
        );
    };

    return (
        <PlatformLayout>
            <div className="space-y-4">
                <div>
                    <Link
                        href={route('platform.businesses.index')}
                        className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <ArrowLeftIcon className="h-4 w-4" />
                        Businesses
                    </Link>

                    <h1 className="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">
                        Dashboard sections — {business.name}
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Switch parts of the vendor dashboard on or off for this
                        business. A section that is off disappears from their
                        menu and its pages stop resolving.
                        {business.plan && (
                            <>
                                {' '}
                                Plan: <strong>{business.plan}</strong>.
                            </>
                        )}
                        {business.business_type && (
                            <>
                                {' '}
                                Type: <strong>{business.business_type}</strong>.
                            </>
                        )}
                    </p>
                </div>

                <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                    {modules.map((module) => (
                        <div
                            key={module.id}
                            className="flex flex-wrap items-center gap-4 border-b border-gray-100 bg-white p-4 last:border-b-0 dark:border-gray-800 dark:bg-gray-900"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        {module.name}
                                    </p>
                                    <BiBadge
                                        variant={
                                            module.enabled
                                                ? 'success'
                                                : 'neutral'
                                        }
                                    >
                                        {module.enabled ? 'On' : 'Off'}
                                    </BiBadge>
                                    {module.override !== null && (
                                        <BiBadge variant="info">
                                            Override
                                        </BiBadge>
                                    )}
                                </div>
                                {module.description && (
                                    <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                        {module.description}
                                    </p>
                                )}
                                {/*
                                  The reason matters more than the state.
                                  With global, business-type, plan and
                                  per-business layers all in play, "Off" on
                                  its own gives an admin no idea which one to
                                  change.
                                */}
                                <p className="mt-1 text-xs text-gray-400">
                                    {module.reason}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                <BiButton
                                    variant={
                                        module.override === true
                                            ? 'primary'
                                            : 'secondary'
                                    }
                                    onClick={() => set(module, true)}
                                >
                                    On
                                </BiButton>
                                <BiButton
                                    variant={
                                        module.override === false
                                            ? 'danger'
                                            : 'secondary'
                                    }
                                    onClick={() => set(module, false)}
                                >
                                    Off
                                </BiButton>
                                {/*
                                  Clearing is not the same as switching off:
                                  it removes this business's exception so the
                                  section follows their plan and type again.
                                */}
                                <BiButton
                                    variant="secondary"
                                    disabled={module.override === null}
                                    onClick={() => set(module, null)}
                                >
                                    Follow plan
                                </BiButton>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </PlatformLayout>
    );
}
