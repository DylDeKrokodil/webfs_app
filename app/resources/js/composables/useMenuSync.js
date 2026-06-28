import { toastService } from "../services/toastService";

export function useMenuSync(items, orderLines = null) {
    const sortItems = () => {
        items.value.sort((a, b) => {
            // 1. Category Sort Order
            const catA = a.category_sort_order ?? 999;
            const catB = b.category_sort_order ?? 999;
            if (catA !== catB) return catA - catB;

            // 2. Number IS NULL last
            const hasNumA = a.number !== null && a.number !== undefined;
            const hasNumB = b.number !== null && b.number !== undefined;
            if (hasNumA && !hasNumB) return -1;
            if (!hasNumA && hasNumB) return 1;

            // 3. Number (numeric)
            if (hasNumA && hasNumB) {
                const numA = Number(a.number);
                const numB = Number(b.number);
                if (numA !== numB) return numA - numB;
            }

            // 4. Suffix (string)
            const sufA = String(a.suffix ?? "");
            const sufB = String(b.suffix ?? "");
            const sufComp = sufA.localeCompare(sufB, "nl-NL", {
                numeric: true,
            });
            if (sufComp !== 0) return sufComp;

            // 5. Name (string)
            return String(a.name ?? "").localeCompare(
                String(b.name ?? ""),
                "nl-NL",
            );
        });
    };

    const handleMenuItemUpdated = (data) => {
        console.log("Real-time: Menu Item Updated", data);
        const index = items.value.findIndex((item) => item.id === data.id);

        if (data.is_active) {
            if (index !== -1) {
                items.value[index] = data;
            } else {
                items.value.push(data);
            }
            sortItems();
        } else if (index !== -1) {
            items.value.splice(index, 1);

            // handle removal from current order (tablet view)
            if (orderLines) {
                const orderIndex = orderLines.value.findIndex(
                    (line) => line.id === data.id,
                );
                if (orderIndex !== -1) {
                    orderLines.value.splice(orderIndex, 1);
                    toastService.info(
                        `'${data.name}' is niet meer beschikbaar en is uit uw bestelling verwijderd.`,
                    );
                }
            }
        }
    };

    return {
        sortItems,
        handleMenuItemUpdated,
    };
}
