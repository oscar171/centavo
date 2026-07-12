export default function AppLogo() {
    return (
        <>
            <img
                src="/logo.png"
                alt="Al centavo"
                className="size-8 shrink-0 rounded-md object-contain"
            />
            <div className="ml-1 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">Al centavo</span>
                <span className="truncate text-[10px] text-muted-foreground">
                    Powered by SynapticSpark
                </span>
            </div>
        </>
    );
}
