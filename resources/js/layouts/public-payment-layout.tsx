export default function PublicPaymentLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <main className="min-h-screen bg-background px-4 py-8 text-foreground sm:px-6 lg:px-8">
            <div className="mx-auto w-full max-w-4xl">{children}</div>
        </main>
    );
}
