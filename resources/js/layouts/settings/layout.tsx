import type { PropsWithChildren } from 'react';

export default function SettingsLayout({ children }: PropsWithChildren) {
    return (
        <div className="px-4 py-6">
            <div className="flex flex-col">
                <div className="w-full">
                    <section className="space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
