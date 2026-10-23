export interface Base64ImageToolProps {
    isAuthenticated: boolean;
    isPaidPlan: boolean;
    maxUploadBytes: number;
    downloadThresholdBytes: number;
    retentionHours: number;
}

export type OutputFormat = 'data-uri' | 'img' | 'css';

export interface ConversionItem {
    id: string;
    filename: string;
    mime_type: string;
    file_size_bytes: number;
    base64_size_bytes: number;
    is_paid_plan?: boolean;
    is_expired?: boolean;
    created_at: string;
    download_url: string;
    preview_url?: string;
    data_uri?: string | null;
    direct_download?: boolean;
}

export interface AnonymousConversionRecord {
    id: string;
    filename: string;
    mime_type: string;
    file_size_bytes: number;
    base64_size_bytes: number;
    created_at: string;
    download_url: string;
    preview_url: string;
}
