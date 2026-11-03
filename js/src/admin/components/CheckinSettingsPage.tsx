import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';

export default class CheckinSettingsPage extends ExtensionPage {
  content(vnode?: Mithril.VnodeDOM<any, this>): JSX.Element {
    const checkinColor = this.setting('primexcalibur-checkin-with-history.checkinColor', '#2756c6')();
    const supplementColor = this.setting('primexcalibur-checkin-with-history.supplementaryColor', '#ff9900')();

    return (
      <div className="ExtensionPage-settings CheckinSettingsPage">
        <div className="container">
          <div className="Form">
            <fieldset>
              <legend>{app.translator.trans('primexcalibur-checkin-with-history.admin.sections.daily')}</legend>
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.checkinRewardMoney',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.daily-reward-money'),
                type: 'number',
                step: 'any',
                default: '0',
              })}
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.checkinTimeZone',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.timezone'),
                type: 'number',
                default: '0',
              })}
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.autoCheckIn',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.auto-check-in'),
                type: 'switch',
              })}
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.autoCheckInDelay',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.auto-check-in-delay'),
                type: 'number',
                default: '0',
              })}
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.checkinSuccessPromptType',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.check-in-success-prompt-type'),
                type: 'select',
                default: '0',
                options: {
                  '0': app.translator.trans('primexcalibur-checkin-with-history.admin.settings.None'),
                  '1': app.translator.trans('primexcalibur-checkin-with-history.admin.settings.Alert'),
                  '2': app.translator.trans('primexcalibur-checkin-with-history.admin.settings.Modal'),
                },
              })}
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.checkinSuccessPromptText',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.check-in-success-prompt-text'),
                help: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.check-in-success-prompt-example-text'),
                type: 'text',
              })}
              {this.buildSettingComponent({
                setting: 'primexcalibur-checkin-with-history.checkinSuccessPromptRewardText',
                label: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.check-in-success-prompt-reward-text'),
                help: app.translator.trans('primexcalibur-checkin-with-history.admin.settings.check-in-success-prompt-example-reward-text'),
                type: 'text',
              })}
            </fieldset>

            <fieldset>
              <legend>{app.translator.trans('primexcalibur-checkin-with-history.admin.sections.rules')}</legend>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.checkin-range')}</label>
                  <input className="FormControl" type="number" bidi={this.setting('primexcalibur-checkin-with-history.checkinRange', '0')} min="0" />
                  <div className="helpText">
                    {app.translator.trans('primexcalibur-checkin-with-history.admin.settings.checkin-range-requirement')}
                  </div>
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.max-supplementary-checkin')}</label>
                  <input
                    className="FormControl"
                    type="number"
                    bidi={this.setting('primexcalibur-checkin-with-history.maxSupplementaryCheckin', '0')}
                    min="0"
                  />
                  <div className="helpText">
                    {app.translator.trans('primexcalibur-checkin-with-history.admin.settings.max-supplementary-checkin-requirement')}
                  </div>
                </div>
              </div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.min-supplementary-date')}</label>
                  <input className="FormControl" type="date" bidi={this.setting('primexcalibur-checkin-with-history.minSupplementaryDate')} />
                </div>

                <div className="Form-group CheckinAdminSwitchGroup">
                  <Switch
                    state={this.setting('primexcalibur-checkin-with-history.spanDayCheckin')() === '1'}
                    onchange={(val: boolean) => {
                      this.setting('primexcalibur-checkin-with-history.spanDayCheckin')(val ? '1' : '0');
                    }}
                  >
                    {app.translator.trans('primexcalibur-checkin-with-history.admin.settings.span-day-checkin')}
                  </Switch>
                  <div className="helpText">
                    {app.translator.trans('primexcalibur-checkin-with-history.admin.settings.span-day-checkin-requirement')}
                  </div>
                </div>
              </div>
            </fieldset>

            <fieldset>
              <legend>{app.translator.trans('primexcalibur-checkin-with-history.admin.sections.economy')}</legend>

              <div className="Form-group">
                <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.reward-money')}</label>
                <input className="FormControl" type="number" bidi={this.setting('primexcalibur-checkin-with-history.rewardMoney', '0')} min="0" />
                <div className="helpText">{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.reward-money-requirement')}</div>
              </div>
            </fieldset>

            <fieldset>
              <legend>{app.translator.trans('primexcalibur-checkin-with-history.admin.sections.appearance')}</legend>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.checkin-color')}</label>
                  <div className="CheckinColorControl">
                    <span className="CheckinColorPreview" style={{ backgroundColor: checkinColor || '#2756c6' }} />
                    <input
                      className="FormControl"
                      type="text"
                      bidi={this.setting('primexcalibur-checkin-with-history.checkinColor', '#2756c6')}
                      placeholder="#2756c6"
                    />
                  </div>
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.supplementary-color')}</label>
                  <div className="CheckinColorControl">
                    <span className="CheckinColorPreview" style={{ backgroundColor: supplementColor || '#ff9900' }} />
                    <input
                      className="FormControl"
                      type="text"
                      bidi={this.setting('primexcalibur-checkin-with-history.supplementaryColor', '#ff9900')}
                      placeholder="#ff9900"
                    />
                  </div>
                </div>
              </div>
            </fieldset>

            <fieldset>
              <legend>{app.translator.trans('primexcalibur-checkin-with-history.admin.sections.cdn')}</legend>
              <div className="helpText">{app.translator.trans('primexcalibur-checkin-with-history.admin.sections.cdn-help')}</div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.cdn-fullcalendar-url')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('primexcalibur-checkin-with-history.cdnFullcalendarUrl')}
                    placeholder="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"
                  />
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.cdn-fullcalendar-sri')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('primexcalibur-checkin-with-history.cdnFullcalendarSri')}
                    placeholder="sha384-..."
                  />
                </div>
              </div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.cdn-locales-url')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('primexcalibur-checkin-with-history.cdnLocalesUrl')}
                    placeholder="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"
                  />
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('primexcalibur-checkin-with-history.admin.settings.cdn-locales-sri')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('primexcalibur-checkin-with-history.cdnLocalesSri')}
                    placeholder="sha384-..."
                  />
                </div>
              </div>
            </fieldset>

            <div className="Form-group">{this.submitButton()}</div>
          </div>
        </div>
      </div>
    );
  }
}
