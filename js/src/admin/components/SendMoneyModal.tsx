import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';

export default class SendMoneyModal extends Modal {
  range: boolean = false;
  amount: string = '';
  username: string = '';
  loading: boolean = false;

  className(): string {
    return 'Modal--small';
  }

  title(): Mithril.Children {
    return app.translator.trans('mattoid-daily-check-in-history.admin.settings.complimentary-supplementary-card');
  }

  content(): Mithril.Children {
    return (
      <div className="Modal-body">
        <div className="Form-group">
          <Switch
            state={this.range}
            onchange={(value: boolean) => {
              this.range = value;
            }}
            disabled={this.loading}
          >
            {app.translator.trans('mattoid-daily-check-in-history.admin.settings.user-all')}
          </Switch>
          <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.range-help')}</div>
        </div>

        {!this.range && (
          <div className="Form-group">
            <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.username')}</label>
            <input
              className="FormControl"
              type="text"
              value={this.username}
              oninput={(event: Event) => {
                this.username = (event.target as HTMLInputElement).value;
              }}
              disabled={this.loading}
            />
            <div className="helpText">{app.translator.trans('mattoid-daily-check-in-history.admin.settings.user-help')}</div>
          </div>
        )}

        <div className="Form-group">
          <label>{app.translator.trans('mattoid-daily-check-in-history.admin.settings.give-number')}</label>
          <input
            className="FormControl"
            type="number"
            value={this.amount}
            oninput={(event: Event) => {
              this.amount = (event.target as HTMLInputElement).value;
            }}
            min="1"
            step="1"
            disabled={this.loading}
          />
        </div>

        <div className="Form-group">
          <Button
            type="submit"
            className="Button Button--primary"
            loading={this.loading}
            disabled={parseInt(this.amount || '0', 10) <= 0 || (!this.range && !this.username.trim())}
          >
            {app.translator.trans('mattoid-daily-check-in-history.admin.settings.submit-give')}
          </Button>
        </div>
      </div>
    );
  }

  request() {
    return app.request<{ userMatchCount: number }>({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/give/checkin/card',
      errorHandler: this.onerror.bind(this),
      body: {
        username: this.username,
        amount: parseInt(this.amount, 10),
        range: this.range,
      },
    });
  }

  onsubmit(event: Event): void {
    event.preventDefault();
    this.loading = true;

    this.request()
      .then((payload) => {
        this.hide();

        app.alerts.show(
          { type: 'success' },
          app.translator.trans('mattoid-daily-check-in-history.admin.modal.give-success', {
            count: payload.userMatchCount,
          })
        );
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }
}
