function formatFileSize(filesize) {
    const kb = 1024;
    const mb = 1024 * kb;
    const gb = 1024 * mb;

    let r = parseInt(filesize / gb);
    if(r > 0) {
        const f = parseFloat(filesize / gb);
        return `${f.toFixed(2)}GB`;
    } else {
        r = parseInt(filesize / mb);
        if(r > 0) {
            const f = parseFloat(filesize / mb);
            return `${f.toFixed(2)}MB`;
        } else {
            r = parseInt(filesize / kb);
            if(r > 0) {
                const f = parseFloat(filesize / kb);
                return `${f.toFixed(2)}KB`;
            } else {
                return `${filesize}B`
            }
        }
    }
}

function storagePath(path) {
    return `/storage/${path}`;
}