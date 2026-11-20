import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  Alteration,
  Fitting,
  ProductionDashboard,
  ProductionJob,
  ProductionJobListResponse,
  ProductionStage,
  QualityCheck,
  TeamMember,
} from '../../types';
import { Alert, EmptyState, Panel, Stat, formatDate, labelValue } from '../ui';

const JOB_STATUSES = ['queued', 'in_progress', 'blocked', 'completed', 'cancelled'];
const TASK_STATUSES = ['pending', 'in_progress', 'done', 'cancelled'];

/**
 * Production: one job per garment, its tasks, fittings, alterations and checks.
 *
 * Every counter and list comes from the server; the view never infers that a
 * garment is ready because its tasks look done.
 */
export function ProductionView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [dashboard, setDashboard] = useState<ProductionDashboard | null>(null);
  const [jobs, setJobs] = useState<ProductionJobListResponse | null>(null);
  const [stages, setStages] = useState<ProductionStage[]>([]);
  const [members, setMembers] = useState<TeamMember[]>([]);
  const [detail, setDetail] = useState<ProductionJob | null>(null);
  const [fittings, setFittings] = useState<Fitting[]>([]);
  const [checks, setChecks] = useState<QualityCheck[]>([]);
  const [alterations, setAlterations] = useState<Alteration[]>([]);
  const [status, setStatus] = useState('all');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [planning, setPlanning] = useState(false);
  const [planItemId, setPlanItemId] = useState(0);
  const [planAssignee, setPlanAssignee] = useState('0');
  const [planDue, setPlanDue] = useState('');
  const [planNotes, setPlanNotes] = useState('');
  const [newStage, setNewStage] = useState('');
  const [newStageOrder, setNewStageOrder] = useState('100');

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('production.manage');
  const canViewMaterials =
    active.permissions.includes('*') || active.permissions.includes('materials.view');
  const canViewFittings =
    active.permissions.includes('*') || active.permissions.includes('fittings.view');
  const canViewQuality =
    active.permissions.includes('*') || active.permissions.includes('quality.view');
  const canManageFittings =
    active.permissions.includes('*') || active.permissions.includes('fittings.manage');
  const canManageQuality =
    active.permissions.includes('*') || active.permissions.includes('quality.manage');

  const load = useCallback(async () => {
    try {
      const [board, list, stageList] = await Promise.all([
        client.productionDashboard(),
        client.productionJobs({ status }),
        client.productionStages(),
      ]);

      setDashboard(board);
      setJobs(list);
      setStages(stageList.stages);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Production could not be loaded.');
    }
  }, [client, status]);

  useEffect(() => {
    if (detail === null) {
      void load();
    }
  }, [load, detail]);

  useEffect(() => {
    client
      .team()
      .then((result) => setMembers(result.members))
      .catch(() => setMembers([]));
  }, [client]);

  /**
   * Load the fittings, alterations and checks that belong to one garment.
   * Sections the viewer may not read are skipped rather than failing the panel.
   */
  const loadRelated = useCallback(
    async (job: ProductionJob) => {
      const [fittingList, checkList, alterationList] = await Promise.all([
        canViewFittings ? client.fittings({ orderItemId: job.orderItemId }) : Promise.resolve(null),
        canViewQuality
          ? client.qualityChecks({ orderItemId: job.orderItemId })
          : Promise.resolve(null),
        canViewFittings
          ? client.alterations({ orderItemId: job.orderItemId })
          : Promise.resolve(null),
      ]);

      setFittings(fittingList?.fittings ?? []);
      setChecks(checkList?.checks ?? []);
      setAlterations(alterationList?.alterations ?? []);
    },
    [client, canViewFittings, canViewQuality]
  );

  const refreshDetail = async (job: ProductionJob) => {
    setDetail(job);
    await loadRelated(job);
  };

  const open = async (jobId: number) => {
    setBusy(true);

    try {
      await refreshDetail(await client.productionJob(jobId));
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The job could not be loaded.');
    } finally {
      setBusy(false);
    }
  };

  const plan = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (planItemId <= 0) {
      setError('Choose a garment to plan production for.');
      return;
    }

    setBusy(true);

    try {
      const created = await client.createProductionJob({
        orderItemId: planItemId,
        assignedTo: Number(planAssignee) || 0,
        dueDate: planDue,
        notes: planNotes,
      });
      setPlanning(false);
      setPlanItemId(0);
      setPlanAssignee('0');
      setPlanDue('');
      setPlanNotes('');
      await load();
      await open(created.id);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The production job could not be created.');
    } finally {
      setBusy(false);
    }
  };

  const updateJob = async (input: {
    status?: string;
    assignedTo?: number | string;
    startDate?: string;
    dueDate?: string;
    notes?: string;
  }) => {
    if (detail === null) {
      return;
    }

    setBusy(true);

    try {
      const updated = await client.updateProductionJob(detail.id, input);
      await refreshDetail(updated);
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The job could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  const addStage = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      await client.createProductionStage({
        name: newStage,
        sequence: Number(newStageOrder) || 0,
      });
      setNewStage('');
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The stage could not be added.');
    } finally {
      setBusy(false);
    }
  };

  const toggleStage = async (stage: ProductionStage) => {
    setBusy(true);

    try {
      await client.updateProductionStage(stage.id, { isActive: !stage.isActive });
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The stage could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  if (detail !== null) {
    return (
      <div className="fashionos-view">
        <button className="fashionos-link-btn" type="button" onClick={() => setDetail(null)}>
          ← All production jobs
        </button>

        {error ? <Alert>{error}</Alert> : null}

        <header>
          <p className="fashionos-eyebrow">
            {detail.orderReference} · {detail.clientName}
          </p>
          <h1 className="fashionos-view__title">{detail.garmentName}</h1>
          <p className="fashionos-view__lead">
            {detail.taskDone} of {detail.taskTotal} tasks done
            {detail.assigneeName ? ` · ${detail.assigneeName}` : ' · unassigned'}
            {detail.orderDueDate ? ` · order due ${detail.orderDueDate}` : ''}
          </p>
        </header>

        <Panel title="Job" description="Only this garment moves when you change job details.">
          <dl className="fashionos-stats">
            <Stat label="Job status" value={labelValue(detail.status)} />
            <Stat label="Garment status" value={labelValue(detail.garmentStatus)} />
            <Stat label="Start" value={detail.startDate || 'Not set'} />
            <Stat label="Due" value={detail.dueDate || 'Not set'} />
          </dl>

          {detail.notes ? <p className="fashionos-note">{detail.notes}</p> : null}

          {canManage ? (
            <div className="fashionos-inline-form">
              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-job-status">
                  Move the job
                </label>
                <select
                  className="fashionos-select"
                  id="fashionos-job-status"
                  value={detail.status}
                  disabled={busy}
                  onChange={(event) => void updateJob({ status: event.target.value })}
                >
                  {JOB_STATUSES.map((choice) => (
                    <option key={choice} value={choice}>
                      {labelValue(choice)}
                    </option>
                  ))}
                </select>
              </div>

              {members.length > 0 ? (
                <div className="fashionos-field">
                  <label className="fashionos-label" htmlFor="fashionos-job-assignee">
                    Assigned to
                  </label>
                  <select
                    className="fashionos-select"
                    id="fashionos-job-assignee"
                    value={detail.assignedTo}
                    disabled={busy}
                    onChange={(event) => void updateJob({ assignedTo: event.target.value })}
                  >
                    <option value={0}>Unassigned</option>
                    {members.map((member) => (
                      <option key={member.userId} value={member.userId}>
                        {member.name}
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}

              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-job-due">
                  Job due date
                </label>
                <input
                  className="fashionos-input"
                  id="fashionos-job-due"
                  type="date"
                  value={detail.dueDate}
                  disabled={busy}
                  onChange={(event) => void updateJob({ dueDate: event.target.value })}
                />
              </div>
            </div>
          ) : null}
        </Panel>

        <Panel
          title="Tasks"
          description="Workflow steps for this garment, in stage order."
          action={
            stages.length > 0 && canManage ? (
              <TaskForm client={client} job={detail} stages={stages} onUpdated={refreshDetail} />
            ) : null
          }
        >
          {detail.tasks.length === 0 ? (
            <EmptyState
              title="No tasks yet"
              detail="Add the stages this garment must pass through so progress is visible."
            />
          ) : (
            <div className="fashionos-table-wrap">
              <table className="fashionos-table">
                <thead>
                  <tr>
                    <th scope="col">Stage</th>
                    <th scope="col">Assignee</th>
                    <th scope="col">Due</th>
                    <th scope="col">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {detail.tasks.map((task) => (
                    <tr key={task.id}>
                      <td className="fashionos-table__strong">{task.stageName}</td>
                      <td className="fashionos-table__muted">
                        {task.assigneeName || 'Unassigned'}
                      </td>
                      <td className="fashionos-table__muted">{task.dueDate || '—'}</td>
                      <td>
                        {canManage ? (
                          <select
                            className="fashionos-select fashionos-select--compact"
                            value={task.status}
                            aria-label={`Status for ${task.stageName}`}
                            onChange={(event) =>
                              void client
                                .updateProductionTask(task.id, { status: event.target.value })
                                .then((job) => refreshDetail(job))
                                .catch((cause: unknown) =>
                                  setError(
                                    cause instanceof Error
                                      ? cause.message
                                      : 'The task could not be updated.'
                                  )
                                )
                            }
                          >
                            {TASK_STATUSES.map((choice) => (
                              <option key={choice} value={choice}>
                                {labelValue(choice)}
                              </option>
                            ))}
                          </select>
                        ) : (
                          labelValue(task.status)
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>

        {canViewFittings ? (
          <Panel title="Fittings" description="Appointments for this garment.">
            <FittingSection
              client={client}
              job={detail}
              fittings={fittings}
              onChange={refreshDetail}
              canManage={canManageFittings}
              onError={setError}
            />
          </Panel>
        ) : null}

        {canViewFittings ? (
          <Panel title="Alterations" description="Changes requested after a fitting.">
            <AlterationSection
              client={client}
              job={detail}
              alterations={alterations}
              onChange={refreshDetail}
              canManage={canManageFittings}
              onError={setError}
            />
          </Panel>
        ) : null}

        {canViewQuality ? (
          <Panel title="Quality checks" description="Checks are appended, never overwritten.">
            <QualitySection
              client={client}
              job={detail}
              checks={checks}
              onChange={refreshDetail}
              canManage={canManageQuality}
              onError={setError}
            />
          </Panel>
        ) : null}

        {canViewMaterials ? (
          <Panel title="Material readiness" description="What this garment is allocated.">
            <GarmentMaterials client={client} itemId={detail.orderItemId} onError={setError} />
          </Panel>
        ) : null}
      </div>
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Production</p>
        <h1 className="fashionos-view__title">Production</h1>
        <p className="fashionos-view__lead">
          Every garment moves through its own workflow for {active.tenant.name}.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      {dashboard ? (
        <Panel title="Today" description="Counters are calculated by the server on load.">
          <dl className="fashionos-stats">
            <Stat
              label="Active jobs"
              value={String(
                (dashboard.counts.jobs.queued ?? 0) +
                  (dashboard.counts.jobs.in_progress ?? 0) +
                  (dashboard.counts.jobs.blocked ?? 0)
              )}
            />
            <Stat label="Overdue" value={String(dashboard.counts.overdueJobs)} />
            <Stat label="Unassigned" value={String(dashboard.counts.unassignedJobs)} />
            <Stat label="Open tasks" value={String(dashboard.counts.openTasks)} />
            <Stat label="Unplanned garments" value={String(dashboard.counts.unplannedGarments)} />
            <Stat label="Failed checks" value={String(dashboard.counts.failedChecks)} />
          </dl>

          {dashboard.unplannedGarments.length > 0 ? (
            <div className="fashionos-subsection">
              <h3 className="fashionos-subhead">Garments without a production job</h3>
              <ul className="fashionos-note-block">
                {dashboard.unplannedGarments.map((garment) => (
                  <li key={garment.id}>
                    {garment.garmentName} · {garment.orderReference} · {garment.clientName}
                    {canManage ? (
                      <button
                        className="fashionos-link-btn"
                        type="button"
                        onClick={() => {
                          setPlanItemId(garment.id);
                          setPlanning(true);
                        }}
                      >
                        Plan production
                      </button>
                    ) : null}
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          <div className="fashionos-subsection">
            <h3 className="fashionos-subhead">Next fittings</h3>
            {dashboard.upcomingFittings.length === 0 ? (
              <p className="fashionos-hint">Nothing scheduled in the next two weeks.</p>
            ) : (
              <ul className="fashionos-note-block">
                {dashboard.upcomingFittings.map((fitting) => (
                  <li key={fitting.id}>
                    {fitting.garmentName} · {fitting.clientName} · {formatDate(fitting.scheduledAt)}
                  </li>
                ))}
              </ul>
            )}
          </div>

          <div className="fashionos-subsection">
            <h3 className="fashionos-subhead">Low stock</h3>
            {dashboard.lowStock.length === 0 ? (
              <p className="fashionos-hint">No materials are at their reorder level.</p>
            ) : (
              <ul className="fashionos-note-block">
                {dashboard.lowStock.map((material) => (
                  <li key={material.id}>
                    {material.name} · {material.quantityOnHand} {material.unit} left (reorder at{' '}
                    {material.reorderLevel})
                  </li>
                ))}
              </ul>
            )}
          </div>
        </Panel>
      ) : null}

      <Panel
        title="Jobs"
        description={jobs ? `${jobs.total} ${jobs.total === 1 ? 'job' : 'jobs'}` : undefined}
        action={
          canManage ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              disabled={dashboard === null || dashboard.unplannedGarments.length === 0}
              onClick={() => setPlanning((value) => !value)}
            >
              {planning ? 'Close' : 'Plan production'}
            </button>
          ) : null
        }
      >
        {planning && dashboard ? (
          <form className="fashionos-form" onSubmit={plan}>
            <div className="fashionos-form__row">
              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-plan-garment">
                  Garment
                </label>
                <select
                  className="fashionos-select"
                  id="fashionos-plan-garment"
                  value={planItemId}
                  onChange={(event) => setPlanItemId(Number(event.target.value))}
                >
                  <option value={0}>Choose a garment</option>
                  {dashboard.unplannedGarments.map((garment) => (
                    <option key={garment.id} value={garment.id}>
                      {garment.orderReference} · {garment.garmentName} · {garment.clientName}
                    </option>
                  ))}
                </select>
              </div>

              {members.length > 0 ? (
                <div className="fashionos-field">
                  <label className="fashionos-label" htmlFor="fashionos-plan-assignee">
                    Assign to
                  </label>
                  <select
                    className="fashionos-select"
                    id="fashionos-plan-assignee"
                    value={planAssignee}
                    onChange={(event) => setPlanAssignee(event.target.value)}
                  >
                    <option value="0">Unassigned</option>
                    {members.map((member) => (
                      <option key={member.userId} value={member.userId}>
                        {member.name}
                      </option>
                    ))}
                  </select>
                </div>
              ) : null}

              <div className="fashionos-field">
                <label className="fashionos-label" htmlFor="fashionos-plan-due">
                  Job due date
                </label>
                <input
                  className="fashionos-input"
                  id="fashionos-plan-due"
                  type="date"
                  value={planDue}
                  onChange={(event) => setPlanDue(event.target.value)}
                />
              </div>
            </div>

            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-plan-notes">
                Notes
              </label>
              <textarea
                className="fashionos-input fashionos-textarea"
                id="fashionos-plan-notes"
                value={planNotes}
                maxLength={2000}
                onChange={(event) => setPlanNotes(event.target.value)}
              />
            </div>

            <div className="fashionos-actions">
              <button
                className="fashionos-btn fashionos-btn--primary"
                type="submit"
                disabled={busy || planItemId <= 0}
              >
                {busy ? 'Saving…' : 'Create job'}
              </button>
            </div>
          </form>
        ) : null}

        <form className="fashionos-toolbar" onSubmit={(event) => event.preventDefault()}>
          <div className="fashionos-toolbar__group">
            <label className="fashionos-label" htmlFor="fashionos-job-filter">
              Status
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-job-filter"
              value={status}
              onChange={(event) => setStatus(event.target.value)}
            >
              <option value="all">All jobs</option>
              {JOB_STATUSES.map((choice) => (
                <option key={choice} value={choice}>
                  {labelValue(choice)}
                </option>
              ))}
            </select>
          </div>
        </form>

        {jobs === null ? null : jobs.jobs.length === 0 ? (
          <EmptyState
            title="No production jobs"
            detail="Plan a garment to start tracking its stages, fittings and checks."
          />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Garment</th>
                  <th scope="col">Order</th>
                  <th scope="col">Progress</th>
                  <th scope="col">Assignee</th>
                  <th scope="col">Due</th>
                  <th scope="col">Status</th>
                  <th scope="col" className="fashionos-visually-hidden">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                {jobs.jobs.map((job) => (
                  <tr key={job.id}>
                    <td>
                      <span className="fashionos-table__strong">{job.garmentName}</span>
                      <span className="fashionos-table__meta">{job.clientName}</span>
                    </td>
                    <td className="fashionos-table__muted">{job.orderReference}</td>
                    <td className="fashionos-table__muted">
                      {job.taskDone}/{job.taskTotal} tasks
                    </td>
                    <td className="fashionos-table__muted">{job.assigneeName || 'Unassigned'}</td>
                    <td className="fashionos-table__muted">{job.dueDate || '—'}</td>
                    <td>{labelValue(job.status)}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-link-btn"
                        type="button"
                        disabled={busy}
                        onClick={() => void open(job.id)}
                      >
                        Open
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Panel>

      <Panel
        title="Workflow stages"
        description="Stages apply to every job in this workspace. Switch one off instead of deleting it."
      >
        {stages.length === 0 ? (
          <EmptyState title="No stages configured" />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Stage</th>
                  <th scope="col">Order</th>
                  <th scope="col">Active</th>
                </tr>
              </thead>
              <tbody>
                {stages.map((stage) => (
                  <tr key={stage.id}>
                    <td className="fashionos-table__strong">{stage.name}</td>
                    <td className="fashionos-table__muted">{stage.sequence}</td>
                    <td>
                      {canManage ? (
                        <button
                          className="fashionos-btn fashionos-btn--ghost"
                          type="button"
                          disabled={busy}
                          onClick={() => void toggleStage(stage)}
                        >
                          {stage.isActive ? 'Switch off' : 'Switch on'}
                        </button>
                      ) : stage.isActive ? (
                        'Active'
                      ) : (
                        'Off'
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {canManage ? (
          <form className="fashionos-inline-form" onSubmit={addStage}>
            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-stage-name">
                New stage
              </label>
              <input
                className="fashionos-input"
                id="fashionos-stage-name"
                value={newStage}
                maxLength={80}
                onChange={(event) => setNewStage(event.target.value)}
                placeholder="e.g. Hand finishing"
              />
            </div>
            <div className="fashionos-field">
              <label className="fashionos-label" htmlFor="fashionos-stage-order">
                Order
              </label>
              <input
                className="fashionos-input fashionos-input--narrow"
                id="fashionos-stage-order"
                type="number"
                min={0}
                max={9999}
                value={newStageOrder}
                onChange={(event) => setNewStageOrder(event.target.value)}
              />
            </div>
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="submit"
              disabled={busy || newStage.trim() === ''}
            >
              Add stage
            </button>
          </form>
        ) : null}
      </Panel>
    </div>
  );
}

function TaskForm({
  client,
  job,
  stages,
  onUpdated,
}: {
  client: ApiClient;
  job: ProductionJob;
  stages: ProductionStage[];
  onUpdated: (job: ProductionJob) => Promise<void>;
}) {
  const [stageId, setStageId] = useState(stages[0]?.id ?? 0);
  const [dueDate, setDueDate] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      const updated = await client.addProductionTask(job.id, {
        stageId,
        dueDate,
      });
      await onUpdated(updated);
      setDueDate('');
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The task could not be added.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <form className="fashionos-inline-form" onSubmit={submit}>
      {error ? <Alert>{error}</Alert> : null}
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-task-stage">
          Add task
        </label>
        <select
          className="fashionos-select fashionos-select--compact"
          id="fashionos-task-stage"
          value={stageId}
          onChange={(event) => setStageId(Number(event.target.value))}
        >
          {stages
            .filter((stage) => stage.isActive)
            .map((stage) => (
              <option key={stage.id} value={stage.id}>
                {stage.name}
              </option>
            ))}
        </select>
      </div>
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-task-due">
          Due
        </label>
        <input
          className="fashionos-input fashionos-input--narrow"
          id="fashionos-task-due"
          type="date"
          value={dueDate}
          onChange={(event) => setDueDate(event.target.value)}
        />
      </div>
      <button className="fashionos-btn fashionos-btn--ghost" type="submit" disabled={busy}>
        {busy ? 'Adding…' : 'Add task'}
      </button>
    </form>
  );
}

function FittingSection({
  client,
  job,
  fittings,
  onChange,
  canManage,
  onError,
}: {
  client: ApiClient;
  job: ProductionJob;
  fittings: Fitting[];
  onChange: (job: ProductionJob) => Promise<void>;
  canManage: boolean;
  onError: (message: string | null) => void;
}) {
  const [scheduledAt, setScheduledAt] = useState('');
  const [location, setLocation] = useState('');
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      await client.createFitting({
        orderItemId: job.orderItemId,
        scheduledAt: scheduledAt.replace('T', ' '),
        location,
        notes,
      });
      setScheduledAt('');
      setLocation('');
      setNotes('');
      await onChange(await client.productionJob(job.id));
      onError(null);
    } catch (cause: unknown) {
      onError(cause instanceof Error ? cause.message : 'The fitting could not be scheduled.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      {fittings.length === 0 ? (
        <EmptyState title="No fittings yet" />
      ) : (
        <div className="fashionos-table-wrap">
          <table className="fashionos-table">
            <thead>
              <tr>
                <th scope="col">When</th>
                <th scope="col">Where</th>
                <th scope="col">Status</th>
                <th scope="col">Outcome</th>
              </tr>
            </thead>
            <tbody>
              {fittings.map((fitting) => (
                <tr key={fitting.id}>
                  <td>{formatDate(fitting.scheduledAt)}</td>
                  <td className="fashionos-table__muted">{fitting.location || '—'}</td>
                  <td>
                    {canManage && fitting.status === 'scheduled' ? (
                      <select
                        className="fashionos-select fashionos-select--compact"
                        value={fitting.status}
                        aria-label="Fitting status"
                        onChange={(event) =>
                          void client
                            .updateFitting(fitting.id, { status: event.target.value })
                            .then(async () => onChange(await client.productionJob(job.id)))
                            .catch((cause: unknown) =>
                              onError(
                                cause instanceof Error
                                  ? cause.message
                                  : 'The fitting could not be updated.'
                              )
                            )
                        }
                      >
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="missed">Missed</option>
                        <option value="cancelled">Cancelled</option>
                      </select>
                    ) : (
                      labelValue(fitting.status)
                    )}
                  </td>
                  <td className="fashionos-table__muted">{fitting.outcome || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {canManage ? (
        <form className="fashionos-inline-form" onSubmit={submit}>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-fitting-time">
              Schedule a fitting
            </label>
            <input
              className="fashionos-input"
              id="fashionos-fitting-time"
              type="datetime-local"
              value={scheduledAt}
              onChange={(event) => setScheduledAt(event.target.value)}
            />
          </div>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-fitting-location">
              Location
            </label>
            <input
              className="fashionos-input"
              id="fashionos-fitting-location"
              value={location}
              maxLength={191}
              onChange={(event) => setLocation(event.target.value)}
            />
          </div>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-fitting-notes">
              Notes
            </label>
            <input
              className="fashionos-input"
              id="fashionos-fitting-notes"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
            />
          </div>
          <button
            className="fashionos-btn fashionos-btn--ghost"
            type="submit"
            disabled={busy || scheduledAt === ''}
          >
            {busy ? 'Scheduling…' : 'Schedule'}
          </button>
        </form>
      ) : null}
    </>
  );
}

function AlterationSection({
  client,
  job,
  alterations,
  onChange,
  canManage,
  onError,
}: {
  client: ApiClient;
  job: ProductionJob;
  alterations: Alteration[];
  onChange: (job: ProductionJob) => Promise<void>;
  canManage: boolean;
  onError: (message: string | null) => void;
}) {
  const [description, setDescription] = useState('');
  const [dueDate, setDueDate] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      await client.createAlteration({ orderItemId: job.orderItemId, description, dueDate });
      setDescription('');
      setDueDate('');
      await onChange(await client.productionJob(job.id));
      onError(null);
    } catch (cause: unknown) {
      onError(cause instanceof Error ? cause.message : 'The alteration could not be saved.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      {alterations.length === 0 ? (
        <EmptyState title="No alterations recorded" />
      ) : (
        <ul className="fashionos-note-block">
          {alterations.map((alteration) => (
            <li key={alteration.id}>
              {alteration.description} · {labelValue(alteration.status)}
              {alteration.dueDate ? ` · due ${alteration.dueDate}` : ''}
              {canManage && alteration.status !== 'completed' ? (
                <button
                  className="fashionos-link-btn"
                  type="button"
                  onClick={() =>
                    void client
                      .updateAlteration(alteration.id, { status: 'completed' })
                      .then(async () => onChange(await client.productionJob(job.id)))
                      .catch((cause: unknown) =>
                        onError(
                          cause instanceof Error
                            ? cause.message
                            : 'The alteration could not be updated.'
                        )
                      )
                  }
                >
                  Mark complete
                </button>
              ) : null}
            </li>
          ))}
        </ul>
      )}

      {canManage ? (
        <form className="fashionos-inline-form" onSubmit={submit}>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-alteration-description">
              Add an alteration
            </label>
            <input
              className="fashionos-input"
              id="fashionos-alteration-description"
              value={description}
              onChange={(event) => setDescription(event.target.value)}
              placeholder="e.g. Shorten sleeves by 1 cm"
            />
          </div>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-alteration-due">
              Due
            </label>
            <input
              className="fashionos-input fashionos-input--narrow"
              id="fashionos-alteration-due"
              type="date"
              value={dueDate}
              onChange={(event) => setDueDate(event.target.value)}
            />
          </div>
          <button
            className="fashionos-btn fashionos-btn--ghost"
            type="submit"
            disabled={busy || description.trim() === ''}
          >
            Save
          </button>
        </form>
      ) : null}
    </>
  );
}

function QualitySection({
  client,
  job,
  checks,
  onChange,
  canManage,
  onError,
}: {
  client: ApiClient;
  job: ProductionJob;
  checks: QualityCheck[];
  onChange: (job: ProductionJob) => Promise<void>;
  canManage: boolean;
  onError: (message: string | null) => void;
}) {
  const [result, setResult] = useState('passed');
  const [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      await client.createQualityCheck({ orderItemId: job.orderItemId, result, notes });
      setNotes('');
      await onChange(await client.productionJob(job.id));
      onError(null);
    } catch (cause: unknown) {
      onError(cause instanceof Error ? cause.message : 'The check could not be saved.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <>
      {checks.length === 0 ? (
        <EmptyState title="No checks recorded" />
      ) : (
        <ol className="fashionos-timeline">
          {checks.map((check) => (
            <li className="fashionos-timeline__item" key={check.id}>
              <p className="fashionos-timeline__message">
                {labelValue(check.result)} · {check.checkedByName || 'Recorded'}
              </p>
              <p className="fashionos-timeline__meta">
                {formatDate(check.checkedAt)}
                {check.notes ? ` · ${check.notes}` : ''}
              </p>
            </li>
          ))}
        </ol>
      )}

      {canManage ? (
        <form className="fashionos-inline-form" onSubmit={submit}>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-check-result">
              Record a check
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-check-result"
              value={result}
              onChange={(event) => setResult(event.target.value)}
            >
              <option value="passed">Passed</option>
              <option value="failed">Failed</option>
              <option value="needs_rework">Needs rework</option>
            </select>
          </div>
          <div className="fashionos-field">
            <label className="fashionos-label" htmlFor="fashionos-check-notes">
              Notes
            </label>
            <input
              className="fashionos-input"
              id="fashionos-check-notes"
              value={notes}
              onChange={(event) => setNotes(event.target.value)}
            />
          </div>
          <button className="fashionos-btn fashionos-btn--ghost" type="submit" disabled={busy}>
            Save check
          </button>
        </form>
      ) : null}
    </>
  );
}

function GarmentMaterials({
  client,
  itemId,
  onError,
}: {
  client: ApiClient;
  itemId: number;
  onError: (message: string | null) => void;
}) {
  const [allocations, setAllocations] = useState<
    Array<{ id: number; materialName: string; unit: string; quantity: number; source: string }>
  >([]);

  useEffect(() => {
    client
      .orderItemMaterials(itemId)
      .then((result) => setAllocations(result.allocations))
      .catch((cause: unknown) =>
        onError(cause instanceof Error ? cause.message : 'Materials could not be loaded.')
      );
  }, [client, itemId, onError]);

  if (allocations.length === 0) {
    return <p className="fashionos-hint">No material has been allocated to this garment yet.</p>;
  }

  return (
    <ul className="fashionos-note-block">
      {allocations.map((allocation) => (
        <li key={allocation.id}>
          {allocation.materialName} · {allocation.quantity} {allocation.unit} ·{' '}
          {labelSource(allocation.source)}
        </li>
      ))}
    </ul>
  );
}

function labelSource(source: string): string {
  const labels: Record<string, string> = {
    customer_supplied: 'Customer supplied',
    designer_supplied: 'Studio supplied',
    shared_supply: 'Shared supply',
  };

  return labels[source] ?? labelValue(source);
}
