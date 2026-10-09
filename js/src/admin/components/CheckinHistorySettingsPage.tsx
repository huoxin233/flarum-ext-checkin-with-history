import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import Select from 'flarum/common/components/Select';
import extractText from 'flarum/common/utils/extractText';
import SendMoneyModal from './SendMoneyModal';
import type Mithril from 'mithril';

export default class CheckinHistorySettingsPage extends ExtensionPage {
  content(vnode?: Mithril.VnodeDOM<any, this>): JSX.Element {
    const checkinColor = this.setting('mattoid-forum-checkin.checkin-color', '#2756c6')();
    const supplementColor = this.setting('mattoid-forum-checkin.supplementary-color', '#ff9900')();
    const checkinCardOnly = this.setting('mattoid-forum-checkin.checkin-card')() === '1';

    return (
      <div className="ExtensionPage-settings CheckinHistorySettingsPage">
        <div className="container">
          <div className="Form">
            <fieldset>
              <legend>{app.translator.trans('mattoid-daily-check-in-history.admin.sections.daily')}</legend>
              {app.extensionData.getSettings(this.extension.id)?.map(this.buildSettingComponent.bind(this))}
            </fieldset>
            {/* Quick Actions Fieldset */}
            <fieldset>
              <legend>{app.translator.trans('mattoid-daily-check-in-history.admin.sections.quick-actions')}</legend>
              <div className="Form-group">
                <Button
                  className="Button Button--primary"
                  icon="fas fa-gift"
                  onclick={() => {
                    app.modal.show(SendMoneyModal);
                  }}
                >
                  {app.translator.trans('mattoid-daily-check-in-history.admin.settings.complimentary-supplementary-card')}
                </Button>
                <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.sections.quick-actions-desc')}</div>
              </div>
            </fieldset>

            {/* Rules & Limits Fieldset */}
            <fieldset>
              <legend>{app.translator.trans('mattoid-daily-check-in-history.admin.sections.rules')}</legend>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-range')}</label>
                  <input className="FormControl" type="number" bidi={this.setting('mattoid-forum-checkin.checkin-range', '30')} min="1" />
                  <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-range-requirement')}</div>
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.max-supplementary-checkin')}</label>
                  <input className="FormControl" type="number" bidi={this.setting('mattoid-forum-checkin.max-supplementary-checkin', '3')} min="1" />
                  <div className="helpText">
                    {app.translator.trans('mattoid-daily-check-in-history.admin.settings.max-supplementary-checkin-requirement')}
                  </div>
                </div>
              </div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.min-supplementary-date')}</label>
                  <input className="FormControl" type="date" bidi={this.setting('mattoid-forum-checkin.min-supplementary-date')} />
                </div>

                <div className="Form-group CheckinAdminSwitchGroup">
                  <Switch
                    state={this.setting('mattoid-forum-checkin.span-day-checkin')() === '1'}
                    onchange={(val: boolean) => {
                      this.setting('mattoid-forum-checkin.span-day-checkin')(val ? '1' : '0');
                    }}
                  >
                    {app.translator.trans('mattoid-daily-check-in-history.admin.settings.span-day-checkin')}
                  </Switch>
                  <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.span-day-checkin-requirement')}</div>
                </div>
              </div>
            </fieldset>

            {/* Economy & Costs Fieldset */}
            <fieldset>
              <legend>{app.translator.trans('mattoid-daily-check-in-history.admin.sections.economy')}</legend>

              <div className="Form-group">
                <Switch
                  state={checkinCardOnly}
                  onchange={(val: boolean) => {
                    this.setting('mattoid-forum-checkin.checkin-card')(val ? '1' : '0');
                  }}
                >
                  {app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-card')}
                </Switch>
                <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-card-requirement')}</div>
              </div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.consumption')}</label>
                  <input
                    className="FormControl"
                    type="number"
                    bidi={this.setting('mattoid-forum-checkin.consumption', '0')}
                    min="0"
                    disabled={checkinCardOnly}
                  />
                  <div
                    className="helpText"
                    dangerouslySetInnerHTML={{
                      __html: extractText(app.translator.trans('mattoid-daily-check-in-history.admin.settings.reward-money-requirement')),
                    }}
                  />
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.reward-money')}</label>
                  <input className="FormControl" type="number" bidi={this.setting('mattoid-forum-checkin.reward-money', '0')} min="0" />
                  <div
                    className="helpText"
                    dangerouslySetInnerHTML={{
                      __html: extractText(app.translator.trans('mattoid-daily-check-in-history.admin.settings.reward-money-requirement')),
                    }}
                  />
                </div>
              </div>

              <div className="Form-group">
                <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-increase')}</label>
                <input
                  className="FormControl"
                  type="number"
                  bidi={this.setting('mattoid-forum-checkin.checkin-increase', '0')}
                  min="0"
                  disabled={checkinCardOnly}
                />
                <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-increase-requirement')}</div>
              </div>
            </fieldset>

            {/* Appearance & Customization Fieldset */}
            <fieldset>
              <legend>{app.translator.trans('mattoid-daily-check-in-history.admin.sections.appearance')}</legend>

              <div className="Form-group">
                <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-position')}</label>
                <Select
                  value={this.setting('mattoid-forum-checkin.checkin-position', '0')()}
                  options={{
                    '0': extractText(app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-position-options.sidebar')),
                    '1': extractText(app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-position-options.calendar')),
                  }}
                  onchange={(val: string) => {
                    this.setting('mattoid-forum-checkin.checkin-position')(val);
                  }}
                />
                <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-position-requirement')}</div>
              </div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.checkin-color')}</label>
                  <div className="CheckinColorControl">
                    <span className="CheckinColorPreview" style={{ backgroundColor: checkinColor || '#2756c6' }} />
                    <input
                      className="FormControl"
                      type="text"
                      bidi={this.setting('mattoid-forum-checkin.checkin-color', '#2756c6')}
                      placeholder="#2756c6"
                    />
                  </div>
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.supplementary-color')}</label>
                  <div className="CheckinColorControl">
                    <span className="CheckinColorPreview" style={{ backgroundColor: supplementColor || '#ff9900' }} />
                    <input
                      className="FormControl"
                      type="text"
                      bidi={this.setting('mattoid-forum-checkin.supplementary-color', '#ff9900')}
                      placeholder="#ff9900"
                    />
                  </div>
                </div>
              </div>
            </fieldset>

            {/* CDN & Assets Acceleration Fieldset */}
            <fieldset>
              <legend>{app.translator.trans('mattoid-daily-check-in-history.admin.sections.cdn')}</legend>
              <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.sections.cdn-help')}</div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.cdn-fullcalendar-url')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('mattoid-forum-checkin.cdn-fullcalendar-url')}
                    placeholder="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"
                  />
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.cdn-fullcalendar-sri')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('mattoid-forum-checkin.cdn-fullcalendar-sri')}
                    placeholder="sha384-..."
                  />
                </div>
              </div>

              <div className="CheckinAdminGrid">
                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.cdn-locales-url')}</label>
                  <input
                    className="FormControl"
                    type="text"
                    bidi={this.setting('mattoid-forum-checkin.cdn-locales-url')}
                    placeholder="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/locales-all.global.min.js"
                  />
                </div>

                <div className="Form-group">
                  <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.cdn-locales-sri')}</label>
                  <input className="FormControl" type="text" bidi={this.setting('mattoid-forum-checkin.cdn-locales-sri')} placeholder="sha384-..." />
                </div>
              </div>
            </fieldset>

            {/* Native Submit Button */}
            <div className="Form-group">{this.submitButton()}</div>
          </div>
        </div>
      </div>
    );
  }
}
