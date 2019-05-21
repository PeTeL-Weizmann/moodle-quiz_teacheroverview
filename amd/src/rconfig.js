define([], function () {
    window.requirejs.config({
        enforceDefine: false,
        paths: {
            "pnvd3": M.cfg.wwwroot + '/mod/quiz/report/teacheroverview/amd/vendorjs/nvd3.min'
        },
        shim: {
            'pnvd3': {exports: 'pnvd3'}
        }
    });
});