export interface RequestConfig {
    headers?: Record<string, string>;
    params?: Record<string, unknown>;
    responseType?: 'json' | 'blob' | 'text';
    signal?: AbortSignal;
}

export interface ApiResponse<T = unknown> {
    data: T;
    status: number;
    statusText: string;
    headers: Record<string, string>;
    ok: boolean;
}

export class ApiError<T = unknown> extends Error {
    public response?: {
        status: number;
        statusText: string;
        data: T;
        headers: Record<string, string>;
    };
    public status?: number;

    constructor(
        message: string,
        response?: {
            status: number;
            statusText: string;
            data: T;
            headers: Record<string, string>;
        },
    ) {
        super(message);
        this.name = 'ApiError';
        this.response = response;
        this.status = response?.status;
    }
}

function getXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function buildUrl(url: string, params?: Record<string, unknown>): string {
    let resolvedUrl: string;

    if (url.startsWith('http://') || url.startsWith('https://')) {
        resolvedUrl = url;
    } else if (url.startsWith('/api/') || url === '/api') {
        resolvedUrl = url;
    } else {
        const trimmed = url.startsWith('/') ? url : `/${url}`;
        resolvedUrl = `/api${trimmed}`;
    }

    if (!params) return resolvedUrl;

    const searchParams = new URLSearchParams();
    for (const [key, val] of Object.entries(params)) {
        if (val === undefined || val === null || val === '') continue;
        if (Array.isArray(val)) {
            val.forEach((item: unknown) => searchParams.append(`${key}[]`, String(item)));
        } else {
            searchParams.append(key, String(val));
        }
    }

    const query = searchParams.toString();
    return query ? `${resolvedUrl}${resolvedUrl.includes('?') ? '&' : '?'}${query}` : resolvedUrl;
}

async function request<T = unknown>(
    method: string,
    url: string,
    data?: unknown,
    config?: RequestConfig,
): Promise<ApiResponse<T>> {
    const fullUrl = buildUrl(url, config?.params);

    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...config?.headers,
    };

    const xsrfToken = getXsrfToken();
    if (xsrfToken) {
        headers['X-XSRF-TOKEN'] = xsrfToken;
    }

    let body: BodyInit | undefined;
    if (data !== undefined && data !== null) {
        if (
            data instanceof FormData ||
            data instanceof Blob ||
            data instanceof URLSearchParams
        ) {
            body = data;
        } else {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(data);
        }
    }

    let response: Response;
    try {
        response = await fetch(fullUrl, {
            method,
            headers,
            body,
            credentials: 'same-origin',
            signal: config?.signal,
        });
    } catch (networkError: unknown) {
        throw new ApiError(networkError instanceof Error ? networkError.message : 'Network request failed');
    }

    // Convert response headers to lowercase map
    const responseHeaders: Record<string, string> = {};
    response.headers.forEach((val: string, key: string) => {
        responseHeaders[key.toLowerCase()] = val;
    });

    if (response.status === 401 && typeof window !== 'undefined') {
        window.location.href = '/login';
    }

    if (response.status === 419 && typeof window !== 'undefined') {
        window.location.reload();
    }

    let responseData: unknown = null;
    if (response.status !== 204 && response.status !== 205) {
        if (config?.responseType === 'blob') {
            responseData = await response.blob();
        } else if (config?.responseType === 'text') {
            responseData = await response.text();
        } else {
            const text = await response.text();
            if (text) {
                try {
                    responseData = JSON.parse(text);
                } catch {
                    responseData = text;
                }
            }
        }
    }

    if (!response.ok) {
        const message =
            (responseData as { message?: unknown })?.message ||
            `Request failed with status code ${response.status}`;
        throw new ApiError(String(message), {
            status: response.status,
            statusText: response.statusText,
            data: responseData as any,
            headers: responseHeaders,
        });
    }

    return {
        data: responseData as T,
        status: response.status,
        statusText: response.statusText,
        headers: responseHeaders,
        ok: response.ok,
    };
}

const api = {
    get<T = unknown>(url: string, config?: RequestConfig): Promise<ApiResponse<T>> {
        return request<T>('GET', url, undefined, config);
    },
    post<T = unknown>(
        url: string,
        data?: unknown,
        config?: RequestConfig,
    ): Promise<ApiResponse<T>> {
        return request<T>('POST', url, data, config);
    },
    put<T = unknown>(
        url: string,
        data?: unknown,
        config?: RequestConfig,
    ): Promise<ApiResponse<T>> {
        return request<T>('PUT', url, data, config);
    },
    patch<T = unknown>(
        url: string,
        data?: unknown,
        config?: RequestConfig,
    ): Promise<ApiResponse<T>> {
        return request<T>('PATCH', url, data, config);
    },
    delete<T = unknown>(
        url: string,
        config?: RequestConfig,
    ): Promise<ApiResponse<T>> {
        return request<T>('DELETE', url, undefined, config);
    },
    request,
};

export default api;