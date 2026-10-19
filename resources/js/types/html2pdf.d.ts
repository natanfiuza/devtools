declare module 'html2pdf.js' {
    interface Html2PdfOptions {
        margin?: number | [number, number] | [number, number, number, number];
        filename?: string;
        image?: { type?: string; quality?: number };
        enableLinks?: boolean;
        html2canvas?: Record<string, unknown>;
        jsPDF?: {
            unit?: string;
            format?: string | [number, number];
            orientation?: 'portrait' | 'landscape';
            compress?: boolean;
        };
        pagebreak?: {
            mode?: string | string[];
            before?: string | string[];
            after?: string | string[];
            avoid?: string | string[];
        };
    }

    interface Html2PdfInstance {
        from(element: HTMLElement | string): Html2PdfInstance;
        set(options: Html2PdfOptions): Html2PdfInstance;
        toPdf(): Html2PdfInstance;
        output(type: string): Promise<unknown>;
        save(): Promise<void>;
    }

    function html2pdf(): Html2PdfInstance;
    function html2pdf(
        element: HTMLElement | string,
        options?: Html2PdfOptions,
    ): Html2PdfInstance;

    export default html2pdf;
}
