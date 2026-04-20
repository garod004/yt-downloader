<style>
body { background: #f0f4f8; font-family: 'DM Sans', Arial, sans-serif; margin: 0; }

.page-header {
    background: #243447;
    color: #fff;
    padding: 16px 28px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.page-header h1 { margin: 0; font-size: 18px; font-weight: 700; }
.page-header .back-link {
    color: #7f97aa;
    text-decoration: none;
    font-size: 13px;
    margin-left: auto;
}
.page-header .back-link:hover { color: #fff; }

.container { max-width: 1100px; margin: 24px auto; padding: 0 20px; }

.form-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,.07);
    padding: 24px 28px;
    margin-bottom: 20px;
}

.form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
.form-group { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 200px; }
.form-group label { font-size: 12px; font-weight: 600; color: #3d5166; text-transform: uppercase; }
.form-group input,
.form-group select,
.form-group textarea {
    padding: 8px 12px;
    border: 1px solid #c5d5e4;
    border-radius: 6px;
    font-size: 13px;
    color: #2c3e50;
    background: #fff;
}
.form-group input:focus,
.form-group select:focus { outline: none; border-color: #3e79b7; }

.section-title {
    font-size: 12px;
    font-weight: 700;
    color: #3d5166;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 10px;
    padding-bottom: 6px;
    border-bottom: 2px solid #e8eef4;
}

.campos-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
}
.btn-campo {
    padding: 4px 10px;
    font-size: 11px;
    border: 1px solid #c5d5e4;
    border-radius: 4px;
    background: #f7fafd;
    color: #2c3e50;
    cursor: pointer;
    transition: background .12s, border-color .12s;
}
.btn-campo:hover { background: #dbeaf5; border-color: #3e79b7; color: #1c2d3c; }
.campos-grupo-title {
    width: 100%;
    font-size: 11px;
    font-weight: 700;
    color: #7f97aa;
    text-transform: uppercase;
    margin-top: 6px;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: background .15s;
}
.btn-primary { background: #3e79b7; color: #fff; }
.btn-primary:hover { background: #2f5f91; }
.btn-secondary { background: #7f97aa; color: #fff; }
.btn-secondary:hover { background: #5c788e; }

.alert { padding: 12px 18px; border-radius: 6px; font-size: 14px; margin-bottom: 12px; }
.alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.footer-form {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}
</style>
