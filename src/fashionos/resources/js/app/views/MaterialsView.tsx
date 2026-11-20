import { useCallback, useEffect, useState, type FormEvent } from 'react';
import type { ApiClient } from '../../services/api';
import type {
  ActiveTenant,
  AllocationResponse,
  Material,
  MaterialsResponse,
  Order,
} from '../../types';
import { Alert, EmptyState, Panel, Stat, formatDate, labelValue } from '../ui';

const MATERIAL_TYPES = ['fabric', 'accessory', 'consumable'];
const UNITS = ['metre', 'yard', 'piece', 'roll', 'spool', 'kilogram', 'litre'];
const SOURCES = ['designer_supplied', 'customer_supplied', 'shared_supply'];

type GarmentChoice = { id: number; label: string };

/**
 * Materials: the catalogue, stock movements and what each garment consumes.
 *
 * Customer-supplied fabric is recorded against the garment but never added to
 * studio stock, so "quantity on hand" always means fabric the studio owns.
 */
export function MaterialsView({ client, active }: { client: ApiClient; active: ActiveTenant }) {
  const [list, setList] = useState<MaterialsResponse | null>(null);
  const [selected, setSelected] = useState<Material | null>(null);
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');
  const [type, setType] = useState('all');
  const [lowStock, setLowStock] = useState(false);
  const [creating, setCreating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [garments, setGarments] = useState<GarmentChoice[]>([]);
  const [allocationItem, setAllocationItem] = useState(0);
  const [allocation, setAllocation] = useState<AllocationResponse | null>(null);
  const [allocateMaterialId, setAllocateMaterialId] = useState(0);
  const [allocateQuantity, setAllocateQuantity] = useState('1');
  const [allocateSource, setAllocateSource] = useState('designer_supplied');
  const [allocateNotes, setAllocateNotes] = useState('');

  const canManage =
    active.permissions.includes('*') || active.permissions.includes('materials.manage');

  const load = useCallback(async () => {
    try {
      setList(await client.materials({ search, type, lowStock }));
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'Materials could not be loaded.');
    }
  }, [client, search, type, lowStock]);

  useEffect(() => {
    if (selected === null) {
      void load();
    }
  }, [load, selected]);

  useEffect(() => {
    client
      .orders({ perPage: 100 })
      .then((result) => {
        const choices = result.orders.flatMap((order: Order) =>
          order.items.map((item) => ({
            id: item.id,
            label: `${order.reference} · ${item.garmentName} · ${order.clientName}`,
          }))
        );

        setGarments(choices);
      })
      .catch(() => setGarments([]));
  }, [client]);

  useEffect(() => {
    if (allocationItem <= 0) {
      setAllocation(null);
      return;
    }

    client
      .orderItemMaterials(allocationItem)
      .then((result) => {
        setAllocation(result);
        setError(null);
      })
      .catch((cause: unknown) =>
        setError(cause instanceof Error ? cause.message : 'Allocations could not be loaded.')
      );
  }, [client, allocationItem]);

  const open = async (materialId: number) => {
    setBusy(true);

    try {
      setSelected(await client.material(materialId));
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The material could not be loaded.');
    } finally {
      setBusy(false);
    }
  };

  const refreshSelected = async (materialId: number) => {
    const material = await client.material(materialId);
    setSelected(material);
    await load();
  };

  const create = async (input: Parameters<ApiClient['createMaterial']>[0]) => {
    setBusy(true);

    try {
      const material = await client.createMaterial(input);
      setCreating(false);
      await load();
      await open(material.id);
      setError(null);
    } finally {
      setBusy(false);
    }
  };

  const allocate = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (allocationItem <= 0) {
      setError('Choose the garment that uses this material.');
      return;
    }

    setBusy(true);

    try {
      const result = await client.allocateMaterial(allocationItem, {
        materialId: allocateMaterialId,
        quantity: allocateQuantity,
        source: allocateSource,
        notes: allocateNotes,
      });
      setAllocation(result);
      setAllocateNotes('');
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The material could not be allocated.');
    } finally {
      setBusy(false);
    }
  };

  const release = async (allocationId: number) => {
    setBusy(true);

    try {
      setAllocation(await client.releaseMaterialAllocation(allocationId));
      await load();
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The allocation could not be released.');
    } finally {
      setBusy(false);
    }
  };

  if (selected !== null) {
    return (
      <div className="fashionos-view">
        <button className="fashionos-link-btn" type="button" onClick={() => setSelected(null)}>
          ← All materials
        </button>

        {error ? <Alert>{error}</Alert> : null}

        <header>
          <p className="fashionos-eyebrow">
            {labelValue(selected.materialType)} · {selected.unit}
          </p>
          <h1 className="fashionos-view__title">{selected.name}</h1>
          <p className="fashionos-view__lead">
            {selected.quantityOnHand} {selected.unit} on hand
            {selected.lowStock ? ' · at or below the reorder level' : ''}
          </p>
        </header>

        <Panel title="Stock" description="Every change is a movement with a reason.">
          <dl className="fashionos-stats">
            <Stat label="On hand" value={`${selected.quantityOnHand} ${selected.unit}`} />
            <Stat label="Reorder at" value={String(selected.reorderLevel)} />
            <Stat label="Unit cost" value={String(selected.unitCost)} />
            <Stat label="Status" value={selected.isActive ? 'Active' : 'Switched off'} />
          </dl>

          {selected.notes ? <p className="fashionos-note">{selected.notes}</p> : null}

          {canManage ? (
            <div className="fashionos-subsection">
              <MovementForm client={client} material={selected} onSaved={refreshSelected} />
              <MaterialSettings client={client} material={selected} onSaved={refreshSelected} />
            </div>
          ) : null}

          {selected.movements.length === 0 ? (
            <EmptyState title="No stock movements yet" />
          ) : (
            <div className="fashionos-table-wrap">
              <table className="fashionos-table">
                <thead>
                  <tr>
                    <th scope="col">When</th>
                    <th scope="col">Type</th>
                    <th scope="col">Change</th>
                    <th scope="col">Note</th>
                    <th scope="col">By</th>
                  </tr>
                </thead>
                <tbody>
                  {selected.movements.map((movement) => (
                    <tr key={movement.id}>
                      <td className="fashionos-table__muted">{formatDate(movement.createdAt)}</td>
                      <td>{labelValue(movement.type)}</td>
                      <td>
                        {movement.quantity > 0 ? '+' : ''}
                        {movement.quantity} {selected.unit}
                      </td>
                      <td className="fashionos-table__muted">{movement.note || '—'}</td>
                      <td className="fashionos-table__muted">{movement.actorName || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Panel>
      </div>
    );
  }

  return (
    <div className="fashionos-view">
      <header>
        <p className="fashionos-eyebrow">Inventory</p>
        <h1 className="fashionos-view__title">Materials</h1>
        <p className="fashionos-view__lead">
          Fabric, accessories and consumables for {active.tenant.name}, with an auditable stock
          history.
        </p>
      </header>

      {error ? <Alert>{error}</Alert> : null}

      <Panel
        title="Catalogue"
        description={
          list ? `${list.total} ${list.total === 1 ? 'material' : 'materials'}` : undefined
        }
        action={
          canManage ? (
            <button
              className="fashionos-btn fashionos-btn--primary"
              type="button"
              onClick={() => setCreating((value) => !value)}
            >
              {creating ? 'Close' : 'New material'}
            </button>
          ) : null
        }
      >
        {creating ? (
          <div className="fashionos-subsection">
            <MaterialForm busy={busy} onSubmit={create} />
          </div>
        ) : null}

        <form className="fashionos-toolbar" onSubmit={(event) => event.preventDefault()}>
          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-material-search">
              Search materials
            </label>
            <input
              className="fashionos-input"
              id="fashionos-material-search"
              value={searchInput}
              placeholder="Search name or notes"
              onChange={(event) => setSearchInput(event.target.value)}
              onBlur={() => setSearch(searchInput.trim())}
            />
            <button
              className="fashionos-btn fashionos-btn--ghost"
              type="button"
              onClick={() => setSearch(searchInput.trim())}
            >
              Search
            </button>
          </div>

          <div className="fashionos-toolbar__group">
            <label className="fashionos-visually-hidden" htmlFor="fashionos-material-type">
              Material type
            </label>
            <select
              className="fashionos-select fashionos-select--compact"
              id="fashionos-material-type"
              value={type}
              onChange={(event) => setType(event.target.value)}
            >
              <option value="all">All types</option>
              {MATERIAL_TYPES.map((choice) => (
                <option key={choice} value={choice}>
                  {labelValue(choice)}
                </option>
              ))}
            </select>

            <label className="fashionos-label" htmlFor="fashionos-material-low">
              <input
                id="fashionos-material-low"
                type="checkbox"
                checked={lowStock}
                onChange={(event) => setLowStock(event.target.checked)}
              />{' '}
              Low stock only
            </label>
          </div>
        </form>

        {list === null ? null : list.materials.length === 0 ? (
          <EmptyState title="No materials yet" detail="Add the fabric you keep in stock." />
        ) : (
          <div className="fashionos-table-wrap">
            <table className="fashionos-table">
              <thead>
                <tr>
                  <th scope="col">Material</th>
                  <th scope="col">Type</th>
                  <th scope="col">On hand</th>
                  <th scope="col">Reorder at</th>
                  <th scope="col">Unit cost</th>
                  <th scope="col" className="fashionos-visually-hidden">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody>
                {list.materials.map((material) => (
                  <tr key={material.id}>
                    <td>
                      <span className="fashionos-table__strong">{material.name}</span>
                      {material.lowStock ? (
                        <span className="fashionos-table__meta">Low stock</span>
                      ) : null}
                    </td>
                    <td className="fashionos-table__muted">{labelValue(material.materialType)}</td>
                    <td>
                      {material.quantityOnHand} {material.unit}
                    </td>
                    <td className="fashionos-table__muted">{material.reorderLevel}</td>
                    <td className="fashionos-table__muted">{material.unitCost}</td>
                    <td className="fashionos-table__actions">
                      <button
                        className="fashionos-link-btn"
                        type="button"
                        disabled={busy}
                        onClick={() => void open(material.id)}
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
        title="Garment allocations"
        description="Name the supply source so customer fabric is never counted as studio stock."
      >
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-allocation-garment">
            Garment
          </label>
          <select
            className="fashionos-select"
            id="fashionos-allocation-garment"
            value={allocationItem}
            onChange={(event) => setAllocationItem(Number(event.target.value))}
          >
            <option value={0}>Choose a garment</option>
            {garments.map((garment) => (
              <option key={garment.id} value={garment.id}>
                {garment.label}
              </option>
            ))}
          </select>
        </div>

        {allocation ? (
          <>
            {allocation.allocations.length === 0 ? (
              <EmptyState title="Nothing allocated to this garment" />
            ) : (
              <ul className="fashionos-note-block">
                {allocation.allocations.map((line) => (
                  <li key={line.id}>
                    {line.materialName} · {line.quantity} {line.unit} · {labelSource(line.source)}
                    {canManage ? (
                      <button
                        className="fashionos-link-btn"
                        type="button"
                        disabled={busy}
                        onClick={() => void release(line.id)}
                      >
                        Release
                      </button>
                    ) : null}
                  </li>
                ))}
              </ul>
            )}

            {canManage && list && list.materials.length > 0 ? (
              <form className="fashionos-form" onSubmit={allocate}>
                <div className="fashionos-form__row">
                  <div className="fashionos-field">
                    <label className="fashionos-label" htmlFor="fashionos-allocate-material">
                      Material
                    </label>
                    <select
                      className="fashionos-select"
                      id="fashionos-allocate-material"
                      value={allocateMaterialId}
                      onChange={(event) => setAllocateMaterialId(Number(event.target.value))}
                    >
                      <option value={0}>Choose a material</option>
                      {list.materials.map((material) => (
                        <option key={material.id} value={material.id}>
                          {material.name} ({material.quantityOnHand} {material.unit})
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="fashionos-field">
                    <label className="fashionos-label" htmlFor="fashionos-allocate-quantity">
                      Quantity
                    </label>
                    <input
                      className="fashionos-input fashionos-input--narrow"
                      id="fashionos-allocate-quantity"
                      value={allocateQuantity}
                      onChange={(event) => setAllocateQuantity(event.target.value)}
                    />
                  </div>

                  <div className="fashionos-field">
                    <label className="fashionos-label" htmlFor="fashionos-allocate-source">
                      Supplied by
                    </label>
                    <select
                      className="fashionos-select"
                      id="fashionos-allocate-source"
                      value={allocateSource}
                      onChange={(event) => setAllocateSource(event.target.value)}
                    >
                      {SOURCES.map((source) => (
                        <option key={source} value={source}>
                          {labelSource(source)}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="fashionos-field">
                  <label className="fashionos-label" htmlFor="fashionos-allocate-notes">
                    Notes
                  </label>
                  <input
                    className="fashionos-input"
                    id="fashionos-allocate-notes"
                    value={allocateNotes}
                    onChange={(event) => setAllocateNotes(event.target.value)}
                  />
                </div>

                <div className="fashionos-actions">
                  <button
                    className="fashionos-btn fashionos-btn--primary"
                    type="submit"
                    disabled={busy || allocateMaterialId <= 0}
                  >
                    {busy ? 'Allocating…' : 'Allocate material'}
                  </button>
                </div>
              </form>
            ) : null}
          </>
        ) : (
          <p className="fashionos-hint">Choose a garment to see and allocate its materials.</p>
        )}
      </Panel>
    </div>
  );
}

function MovementForm({
  client,
  material,
  onSaved,
}: {
  client: ApiClient;
  material: Material;
  onSaved: (materialId: number) => Promise<void>;
}) {
  const [type, setType] = useState('in');
  const [quantity, setQuantity] = useState('');
  const [note, setNote] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      await client.addMaterialMovement(material.id, { type, quantity, note });
      setQuantity('');
      setNote('');
      setError(null);
      await onSaved(material.id);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The stock movement could not be saved.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <form className="fashionos-inline-form" onSubmit={submit}>
      {error ? <Alert>{error}</Alert> : null}
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-movement-type">
          Stock movement
        </label>
        <select
          className="fashionos-select fashionos-select--compact"
          id="fashionos-movement-type"
          value={type}
          onChange={(event) => setType(event.target.value)}
        >
          <option value="in">Stock in</option>
          <option value="out">Stock out</option>
          <option value="adjustment">Adjustment</option>
        </select>
      </div>
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-movement-quantity">
          Quantity
        </label>
        <input
          className="fashionos-input fashionos-input--narrow"
          id="fashionos-movement-quantity"
          value={quantity}
          onChange={(event) => setQuantity(event.target.value)}
          placeholder={type === 'adjustment' ? 'e.g. -1.5' : 'e.g. 12'}
        />
      </div>
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-movement-note">
          Note
        </label>
        <input
          className="fashionos-input"
          id="fashionos-movement-note"
          value={note}
          maxLength={191}
          onChange={(event) => setNote(event.target.value)}
        />
      </div>
      <button
        className="fashionos-btn fashionos-btn--ghost"
        type="submit"
        disabled={busy || quantity.trim() === ''}
      >
        {busy ? 'Saving…' : 'Record'}
      </button>
    </form>
  );
}

function MaterialSettings({
  client,
  material,
  onSaved,
}: {
  client: ApiClient;
  material: Material;
  onSaved: (materialId: number) => Promise<void>;
}) {
  const [reorderLevel, setReorderLevel] = useState(String(material.reorderLevel));
  const [unitCost, setUnitCost] = useState(String(material.unitCost));
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setBusy(true);

    try {
      await client.updateMaterial(material.id, { reorderLevel, unitCost });
      await onSaved(material.id);
      setError(null);
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The material could not be updated.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <form className="fashionos-inline-form" onSubmit={submit}>
      {error ? <Alert>{error}</Alert> : null}
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-material-reorder">
          Reorder level
        </label>
        <input
          className="fashionos-input fashionos-input--narrow"
          id="fashionos-material-reorder"
          value={reorderLevel}
          onChange={(event) => setReorderLevel(event.target.value)}
        />
      </div>
      <div className="fashionos-field">
        <label className="fashionos-label" htmlFor="fashionos-material-cost">
          Unit cost
        </label>
        <input
          className="fashionos-input fashionos-input--narrow"
          id="fashionos-material-cost"
          value={unitCost}
          onChange={(event) => setUnitCost(event.target.value)}
        />
      </div>
      <button className="fashionos-btn fashionos-btn--ghost" type="submit" disabled={busy}>
        Save thresholds
      </button>
    </form>
  );
}

function MaterialForm({
  busy,
  onSubmit,
}: {
  busy: boolean;
  onSubmit: (input: {
    name: string;
    materialType: string;
    unit: string;
    quantityOnHand: string;
    reorderLevel: string;
    unitCost: string;
  }) => Promise<void>;
}) {
  const [name, setName] = useState('');
  const [materialType, setMaterialType] = useState('fabric');
  const [unit, setUnit] = useState('metre');
  const [quantityOnHand, setQuantityOnHand] = useState('');
  const [reorderLevel, setReorderLevel] = useState('0');
  const [unitCost, setUnitCost] = useState('0');
  const [error, setError] = useState<string | null>(null);

  const submit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    try {
      await onSubmit({
        name,
        materialType,
        unit,
        quantityOnHand,
        reorderLevel,
        unitCost,
      });
    } catch (cause: unknown) {
      setError(cause instanceof Error ? cause.message : 'The material could not be saved.');
    }
  };

  return (
    <form className="fashionos-form" onSubmit={submit}>
      {error ? <Alert>{error}</Alert> : null}
      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-new-material">
            Name
          </label>
          <input
            className="fashionos-input"
            id="fashionos-new-material"
            value={name}
            maxLength={120}
            onChange={(event) => setName(event.target.value)}
          />
        </div>
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-new-material-type">
            Type
          </label>
          <select
            className="fashionos-select"
            id="fashionos-new-material-type"
            value={materialType}
            onChange={(event) => setMaterialType(event.target.value)}
          >
            {MATERIAL_TYPES.map((choice) => (
              <option key={choice} value={choice}>
                {labelValue(choice)}
              </option>
            ))}
          </select>
        </div>
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-new-material-unit">
            Unit
          </label>
          <select
            className="fashionos-select"
            id="fashionos-new-material-unit"
            value={unit}
            onChange={(event) => setUnit(event.target.value)}
          >
            {UNITS.map((choice) => (
              <option key={choice} value={choice}>
                {labelValue(choice)}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="fashionos-form__row">
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-new-material-quantity">
            Opening stock
          </label>
          <input
            className="fashionos-input fashionos-input--narrow"
            id="fashionos-new-material-quantity"
            value={quantityOnHand}
            placeholder="0"
            onChange={(event) => setQuantityOnHand(event.target.value)}
          />
        </div>
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-new-material-reorder">
            Reorder level
          </label>
          <input
            className="fashionos-input fashionos-input--narrow"
            id="fashionos-new-material-reorder"
            value={reorderLevel}
            onChange={(event) => setReorderLevel(event.target.value)}
          />
        </div>
        <div className="fashionos-field">
          <label className="fashionos-label" htmlFor="fashionos-new-material-cost">
            Unit cost
          </label>
          <input
            className="fashionos-input fashionos-input--narrow"
            id="fashionos-new-material-cost"
            value={unitCost}
            onChange={(event) => setUnitCost(event.target.value)}
          />
        </div>
      </div>

      <div className="fashionos-actions">
        <button
          className="fashionos-btn fashionos-btn--primary"
          type="submit"
          disabled={busy || name.trim() === ''}
        >
          {busy ? 'Saving…' : 'Add material'}
        </button>
      </div>
    </form>
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
