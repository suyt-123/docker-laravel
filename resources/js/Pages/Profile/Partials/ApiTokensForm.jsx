import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { useForm } from '@inertiajs/react';

export default function ApiTokensForm({
    tokens = [],
    abilities = {},
    newToken = null,
    className = '',
}) {
    const abilityValues = Object.keys(abilities);
    const { data, setData, post, delete: destroy, processing, errors, reset } =
        useForm({
            name: '',
            abilities: abilityValues,
            expires_at: '',
        });

    const submit = (event) => {
        event.preventDefault();

        post(route('profile.api-tokens.store'), {
            preserveScroll: true,
            onSuccess: () => reset('name', 'expires_at'),
        });
    };

    const toggleAbility = (ability) => {
        setData(
            'abilities',
            data.abilities.includes(ability)
                ? data.abilities.filter((value) => value !== ability)
                : [...data.abilities, ability],
        );
    };

    const revoke = (token) => {
        if (!window.confirm(`確定要撤銷「${token.name}」嗎？`)) {
            return;
        }

        destroy(route('profile.api-tokens.destroy', token.id), {
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    外部 API Token
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    建立給外部系統使用的個人存取 token。Token 權限仍受你的角色權限與下方 API 能力限制。
                </p>
            </header>

            {newToken && (
                <div className="mt-6 rounded-md border border-amber-200 bg-amber-50 p-4">
                    <div className="text-sm font-medium text-amber-900">
                        {newToken.name} 已建立
                    </div>
                    <p className="mt-1 text-sm text-amber-800">
                        這是唯一一次顯示完整 token，離開頁面後無法再次查看。
                    </p>
                    <textarea
                        readOnly
                        value={newToken.plain_text_token}
                        className="mt-3 block min-h-24 w-full rounded-md border-amber-300 bg-white font-mono text-sm text-gray-900 shadow-sm"
                    />
                </div>
            )}

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div>
                    <InputLabel htmlFor="api_token_name" value="Token 名稱" />
                    <TextInput
                        id="api_token_name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        required
                        placeholder="例如：報表串接、外部看板"
                    />
                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel value="API 能力" />
                    <div className="mt-2 space-y-2">
                        {abilityValues.map((ability) => (
                            <label
                                key={ability}
                                className="flex items-start gap-3 rounded-md border border-gray-200 px-3 py-2"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    checked={data.abilities.includes(ability)}
                                    onChange={() => toggleAbility(ability)}
                                />
                                <span>
                                    <span className="block text-sm font-medium text-gray-900">
                                        {abilities[ability]}
                                    </span>
                                    <span className="block font-mono text-xs text-gray-500">
                                        {ability}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                    <InputError className="mt-2" message={errors.abilities} />
                </div>

                <div>
                    <InputLabel htmlFor="api_token_expires_at" value="到期日" />
                    <TextInput
                        id="api_token_expires_at"
                        type="date"
                        className="mt-1 block w-full"
                        value={data.expires_at}
                        onChange={(event) =>
                            setData('expires_at', event.target.value)
                        }
                    />
                    <InputError className="mt-2" message={errors.expires_at} />
                </div>

                <PrimaryButton disabled={processing}>
                    建立 API Token
                </PrimaryButton>
            </form>

            <div className="mt-8">
                <h3 className="text-sm font-semibold text-gray-900">
                    目前有效的 tokens
                </h3>
                <div className="mt-3 space-y-3">
                    {tokens.length === 0 && (
                        <p className="text-sm text-gray-500">
                            尚未建立 API token。
                        </p>
                    )}

                    {tokens.map((token) => (
                        <div
                            key={token.id}
                            className="flex flex-col gap-3 rounded-md border border-gray-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <div className="text-sm font-medium text-gray-900">
                                        {token.name}
                                    </div>
                                    <TokenStatus token={token} />
                                </div>
                                <div className="mt-1 text-xs text-gray-500">
                                    建立 {token.created_at || '未填'} · 最後使用{' '}
                                    {token.last_used_label || '尚未使用'} · 到期{' '}
                                    {token.expires_label || '未設定'}
                                </div>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {token.abilities.map((ability) => (
                                        <span
                                            key={ability}
                                            className="rounded bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700"
                                        >
                                            {ability}
                                        </span>
                                    ))}
                                </div>
                            </div>
                            <DangerButton
                                type="button"
                                disabled={processing}
                                onClick={() => revoke(token)}
                            >
                                撤銷
                            </DangerButton>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function TokenStatus({ token }) {
    const isExpired = token.status === 'expired';

    return (
        <span
            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                isExpired
                    ? 'bg-rose-50 text-rose-700'
                    : 'bg-emerald-50 text-emerald-700'
            }`}
        >
            {isExpired ? '已到期' : '有效'}
        </span>
    );
}
