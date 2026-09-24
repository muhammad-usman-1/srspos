// Cash drawer kick for ESC/POS receipt printers (SpeedX SP210 and compatibles).
//
// The receipt itself is still printed through the browser/Windows driver as before.
// The drawer hangs off the printer's RJ11 port and opens when the printer receives
// "ESC p m t1 t2", which a web page can't slip into a driver print job — so the
// command is sent over a direct connection to the printer instead:
//   - Web Serial: printer exposed as a COM port (USB virtual COM / serial cable)
//   - WebUSB:     printer USB interface reachable by the browser
// The cashier pairs the printer once (browser permission); after that the browser
// remembers it and the drawer opens silently.

const STORE_KEY = "pos_cash_drawer"; // "serial" | "usb" | absent

// ESC p 0 25 250 (pin 2) then ESC p 1 25 250 (pin 5): pulse 50ms on / 500ms off.
// Drawers are wired to one pin or the other; sending both covers either.
const KICK = new Uint8Array([0x1b, 0x70, 0x00, 0x19, 0xfa, 0x1b, 0x70, 0x01, 0x19, 0xfa]);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function remembered() {
    try {
        return localStorage.getItem(STORE_KEY);
    } catch (e) {
        return null;
    }
}

function remember(kind) {
    try {
        if (kind) localStorage.setItem(STORE_KEY, kind);
        else localStorage.removeItem(STORE_KEY);
    } catch (e) {}
}

export function drawerSupported() {
    return !!(navigator.serial || navigator.usb);
}

export function drawerPaired() {
    return !!remembered();
}

// ---- Web Serial --------------------------------------------------------------
async function serialPort() {
    if (!navigator.serial) return null;
    const ports = await navigator.serial.getPorts();
    return ports[0] || null;
}

async function kickSerial(port) {
    // The COM port is shared with the Windows print spooler, which may still be sending
    // the receipt, so opening can briefly fail as "busy" — retry for a few seconds.
    for (let attempt = 0; attempt < 8; attempt++) {
        try {
            await port.open({ baudRate: 9600 });
            break;
        } catch (e) {
            if (e && e.name === "InvalidStateError") break; // already open
            if (attempt === 7) throw e;
            await sleep(600);
        }
    }
    const writer = port.writable.getWriter();
    try {
        await writer.write(KICK);
    } finally {
        writer.releaseLock();
        await port.close().catch(() => {});
    }
}

// ---- WebUSB ------------------------------------------------------------------
async function usbDevice() {
    if (!navigator.usb) return null;
    const devices = await navigator.usb.getDevices();
    return devices[0] || null;
}

async function kickUsb(device) {
    if (!device.opened) await device.open();
    try {
        if (!device.configuration) await device.selectConfiguration(1);
        // prefer the printer-class interface (7), otherwise any with a bulk OUT endpoint
        const ifaces = device.configuration.interfaces;
        const pick = (i) => i.alternate.endpoints.find((e) => e.direction === "out" && e.type === "bulk");
        const iface = ifaces.find((i) => i.alternate.interfaceClass === 7 && pick(i)) || ifaces.find(pick);
        if (!iface) throw new Error("No output endpoint on this USB device.");
        await device.claimInterface(iface.interfaceNumber);
        try {
            await device.transferOut(pick(iface).endpointNumber, KICK);
        } finally {
            await device.releaseInterface(iface.interfaceNumber).catch(() => {});
        }
    } finally {
        await device.close().catch(() => {});
    }
}

// ---- public API --------------------------------------------------------------

// Must be called from a click (the browser shows its device picker).
// kind: "serial" (COM port, recommended on Windows) or "usb".
export async function pairDrawer(kind) {
    if (kind === "serial") {
        if (!navigator.serial) throw new Error("This browser has no Web Serial support — use Chrome or Edge.");
        await navigator.serial.requestPort();
    } else {
        if (!navigator.usb) throw new Error("This browser has no WebUSB support — use Chrome or Edge.");
        await navigator.usb.requestDevice({ filters: [] });
    }
    remember(kind);
}

export function unpairDrawer() {
    remember(null);
}

// Opens the drawer. Throws if nothing is paired or the printer can't be reached.
export async function openDrawer() {
    const kind = remembered();
    if (kind === "serial") {
        const port = await serialPort();
        if (!port) throw new Error("Paired printer port not found — reconnect the cash drawer.");
        return kickSerial(port);
    }
    if (kind === "usb") {
        const device = await usbDevice();
        if (!device) throw new Error("Paired printer not found — reconnect the cash drawer.");
        return kickUsb(device);
    }
    throw new Error("No cash drawer connected.");
}
