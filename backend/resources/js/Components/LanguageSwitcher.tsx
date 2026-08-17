import Dropdown from '@/Components/Dropdown';
import { useLocale } from '@/contexts/LocaleContext';
import { LanguageIcon } from '@heroicons/react/24/outline';

const LANGUAGES = [
    { code: 'en' as const, label: 'English' },
    { code: 'sw' as const, label: 'Kiswahili' },
];

export default function LanguageSwitcher() {
    const { locale, setLocale } = useLocale();

    return (
        <Dropdown>
            <Dropdown.Trigger>
                <button
                    type="button"
                    className="inline-flex items-center gap-1 rounded-md p-2 text-gray-500 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-700 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-300"
                >
                    <LanguageIcon className="h-6 w-6" />
                    <span className="text-xs font-medium uppercase">
                        {locale}
                    </span>
                </button>
            </Dropdown.Trigger>

            <Dropdown.Content align="right" width="48">
                {LANGUAGES.map((language) => (
                    <button
                        key={language.code}
                        type="button"
                        onClick={() => setLocale(language.code)}
                        className={`block w-full px-4 py-2 text-start text-sm leading-5 transition duration-150 ease-in-out hover:bg-gray-100 dark:hover:bg-gray-800 ${
                            locale === language.code
                                ? 'font-semibold text-indigo-600 dark:text-indigo-400'
                                : 'text-gray-700 dark:text-gray-300'
                        }`}
                    >
                        {language.label}
                    </button>
                ))}
            </Dropdown.Content>
        </Dropdown>
    );
}
