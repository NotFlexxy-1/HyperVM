declare module '@novnc/novnc' {
    export interface RFBCredentials {
        username?: string;
        password?: string;
        target?: string;
    }

    export interface RFBOptions {
        shared?: boolean;
        credentials?: RFBCredentials;
        repeaterID?: string;
        wsProtocols?: string[];
    }

    export default class RFB extends EventTarget {
        constructor(target: Element, url: string, options?: RFBOptions);
        viewOnly: boolean;
        focusOnClick: boolean;
        clipViewport: boolean;
        dragViewport: boolean;
        scaleViewport: boolean;
        resizeSession: boolean;
        background: string;
        qualityLevel: number;
        compressionLevel: number;
        disconnect(): void;
        focus(): void;
        blur(): void;
        sendCtrlAltDel(): void;
        sendKey(keysym: number, code: string | null, down?: boolean): void;
        machineShutdown(): void;
        machineReboot(): void;
        machineReset(): void;
        clipboardPasteFrom(text: string): void;
    }
}
