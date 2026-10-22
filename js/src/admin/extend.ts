import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'passkeys.enabled',
      type: 'bool',
      label: app.translator.trans('cengizkemec-passkeys.admin.settings.enable_label'),
    }))
    .setting(() => ({
      setting: 'passkeys.rp_id',
      type: 'text',
      label: app.translator.trans('cengizkemec-passkeys.admin.settings.rp_id_label'),
      help: app.translator.trans('cengizkemec-passkeys.admin.settings.rp_id_help'),
    }))
    .setting(() => ({
      setting: 'passkeys.rp_name',
      type: 'text',
      label: app.translator.trans('cengizkemec-passkeys.admin.settings.rp_name_label'),
      help: app.translator.trans('cengizkemec-passkeys.admin.settings.rp_name_help'),
    }))
    .setting(() => ({
      setting: 'passkeys.origin',
      type: 'text',
      label: app.translator.trans('cengizkemec-passkeys.admin.settings.origin_label'),
      help: app.translator.trans('cengizkemec-passkeys.admin.settings.origin_help'),
    })),
];
