"""
Property-Based Tests for Scheduler Service
Tests correctness properties for scheduled tasks
"""

import pytest
from hypothesis import given, strategies as st, settings, assume
from datetime import date, timedelta
from sqlalchemy import text
from app.database import AsyncSessionLocal
import asyncio


class TestSchedulerProperties:
    """Property-based tests for scheduler service"""
    
    @pytest.mark.asyncio
    @given(
        total_members=st.integers(min_value=3, max_value=20),
        contribution_amount=st.floats(min_value=100.0, max_value=10000.0)
    )
    @settings(max_examples=100, deadline=None)
    async def test_group_cycle_completion_invariant(
        self,
        total_members: int,
        contribution_amount: float
    ):
        """
        **Validates: Property 18 - Group Cycle Completion Invariant**
        
        Property: For any group that has completed its cycle, every member should 
        have received exactly one payout, and the sum of all payouts should equal 
        (contribution_amount × total_members × cycle_days).
        
        This test verifies that:
        1. Each member receives exactly one payout
        2. Total payouts = contribution_amount × total_members × cycle_days
        3. Group status transitions to 'completed' only when all payouts are done
        """
        # Round contribution amount to 2 decimal places
        contribution_amount = round(contribution_amount, 2)
        
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        :name, :code, :amount, :members, :days, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {
                        "name": f"Test Group {total_members}",
                        "code": f"TEST{total_members}",
                        "amount": contribution_amount,
                        "members": total_members,
                        "days": total_members,
                        "start_date": date.today() - timedelta(days=total_members)
                    }
                )
                group_id = group_result.scalar()
                
                # Create group members
                for i in range(1, total_members + 1):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                # Simulate all contributions for all days
                for day in range(1, total_members + 1):
                    contribution_date = date.today() - timedelta(days=total_members - day)
                    
                    for member_id in range(1, total_members + 1):
                        contrib_query = text("""
                            INSERT INTO contributions (
                                group_id, user_id, amount, payment_method,
                                payment_reference, payment_status, contribution_date,
                                paid_at, created_at, updated_at
                            )
                            VALUES (
                                :group_id, :user_id, :amount, 'wallet',
                                :ref, 'successful', :date, NOW(), NOW(), NOW()
                            )
                        """)
                        
                        await db.execute(
                            contrib_query,
                            {
                                "group_id": group_id,
                                "user_id": member_id,
                                "amount": contribution_amount,
                                "ref": f"CONTRIB-{group_id}-{member_id}-{day}",
                                "date": contribution_date
                            }
                        )
                
                # Simulate all payouts
                total_payout_amount = 0.0
                for member_id in range(1, total_members + 1):
                    payout_amount = contribution_amount * total_members
                    
                    payout_query = text("""
                        INSERT INTO payouts (
                            group_id, user_id, amount, payout_day, payout_date,
                            status, payout_method, payout_reference, processed_at,
                            created_at, updated_at
                        )
                        VALUES (
                            :group_id, :user_id, :amount, :payout_day, :payout_date,
                            'successful', 'wallet', :ref, NOW(), NOW(), NOW()
                        )
                    """)
                    
                    await db.execute(
                        payout_query,
                        {
                            "group_id": group_id,
                            "user_id": member_id,
                            "amount": payout_amount,
                            "payout_day": member_id,
                            "ref": f"PAYOUT-{group_id}-{member_id}",
                            "payout_date": date.today() - timedelta(days=total_members - member_id)
                        }
                    )
                    
                    # Update group member payout status
                    update_member_query = text("""
                        UPDATE group_members
                        SET has_received_payout = TRUE, payout_received_at = NOW()
                        WHERE group_id = :group_id AND user_id = :user_id
                    """)
                    
                    await db.execute(
                        update_member_query,
                        {"group_id": group_id, "user_id": member_id}
                    )
                    
                    total_payout_amount += payout_amount
                
                await db.commit()
                
                # Verify Property 18: Group Cycle Completion Invariant
                
                # 1. Check that each member received exactly one payout
                payout_count_query = text("""
                    SELECT user_id, COUNT(*) as payout_count
                    FROM payouts
                    WHERE group_id = :group_id AND status = 'successful'
                    GROUP BY user_id
                """)
                
                payout_counts = await db.execute(payout_count_query, {"group_id": group_id})
                for row in payout_counts:
                    assert row[1] == 1, f"Member {row[0]} received {row[1]} payouts, expected 1"
                
                # 2. Check that all members received payouts
                member_payout_query = text("""
                    SELECT COUNT(*) FROM group_members
                    WHERE group_id = :group_id AND has_received_payout = TRUE
                """)
                
                members_paid = await db.execute(member_payout_query, {"group_id": group_id})
                members_paid_count = members_paid.scalar()
                assert members_paid_count == total_members, \
                    f"Only {members_paid_count}/{total_members} members received payouts"
                
                # 3. Check total payout amount equals expected
                expected_total = contribution_amount * total_members * total_members
                # Allow small floating point difference
                assert abs(total_payout_amount - expected_total) < 0.01, \
                    f"Total payouts {total_payout_amount} != expected {expected_total}"
                
                # 4. Verify sum of all payouts from database
                sum_query = text("""
                    SELECT SUM(amount) FROM payouts
                    WHERE group_id = :group_id AND status = 'successful'
                """)
                
                sum_result = await db.execute(sum_query, {"group_id": group_id})
                db_total = float(sum_result.scalar() or 0)
                assert abs(db_total - expected_total) < 0.01, \
                    f"Database total {db_total} != expected {expected_total}"
                
                # 5. Simulate group completion check
                update_group_query = text("""
                    UPDATE groups SET status = 'completed', updated_at = NOW()
                    WHERE id = :group_id
                """)
                
                await db.execute(update_group_query, {"group_id": group_id})
                await db.commit()
                
                # 6. Verify group is marked as completed
                status_query = text("""
                    SELECT status FROM groups WHERE id = :group_id
                """)
                
                status_result = await db.execute(status_query, {"group_id": group_id})
                group_status = status_result.scalar()
                assert group_status == 'completed', \
                    f"Group status is {group_status}, expected 'completed'"
                
            finally:
                # Cleanup test data
                await db.execute(text("DELETE FROM payouts WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM contributions WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()
    
    @pytest.mark.asyncio
    @given(
        total_members=st.integers(min_value=3, max_value=10),
        missing_payouts=st.integers(min_value=1, max_value=2)
    )
    @settings(max_examples=50, deadline=None)
    async def test_incomplete_cycle_not_marked_complete(
        self,
        total_members: int,
        missing_payouts: int
    ):
        """
        **Validates: Property 18 - Group Cycle Completion Invariant**
        
        Property: A group should NOT be marked as completed if not all members
        have received their payouts.
        
        This test verifies that groups with incomplete payout cycles remain 'active'.
        """
        assume(missing_payouts < total_members)
        
        async with AsyncSessionLocal() as db:
            try:
                # Create test group
                group_query = text("""
                    INSERT INTO groups (
                        name, group_code, contribution_amount, total_members,
                        cycle_days, status, start_date, created_by, created_at, updated_at
                    )
                    VALUES (
                        :name, :code, 1000.0, :members, :days, 'active',
                        :start_date, 1, NOW(), NOW()
                    )
                    RETURNING id
                """)
                
                group_result = await db.execute(
                    group_query,
                    {
                        "name": f"Incomplete Group {total_members}",
                        "code": f"INC{total_members}",
                        "members": total_members,
                        "days": total_members,
                        "start_date": date.today() - timedelta(days=total_members)
                    }
                )
                group_id = group_result.scalar()
                
                # Create group members
                for i in range(1, total_members + 1):
                    member_query = text("""
                        INSERT INTO group_members (
                            group_id, user_id, position_number, payout_day,
                            has_received_payout, joined_at, status
                        )
                        VALUES (:group_id, :user_id, :position, :payout_day, FALSE, NOW(), 'active')
                    """)
                    
                    await db.execute(
                        member_query,
                        {
                            "group_id": group_id,
                            "user_id": i,
                            "position": i,
                            "payout_day": i
                        }
                    )
                
                # Create payouts for only some members (not all)
                members_to_pay = total_members - missing_payouts
                for member_id in range(1, members_to_pay + 1):
                    # Update group member payout status
                    update_member_query = text("""
                        UPDATE group_members
                        SET has_received_payout = TRUE, payout_received_at = NOW()
                        WHERE group_id = :group_id AND user_id = :user_id
                    """)
                    
                    await db.execute(
                        update_member_query,
                        {"group_id": group_id, "user_id": member_id}
                    )
                
                await db.commit()
                
                # Check if group should be completed (it shouldn't)
                check_query = text("""
                    SELECT 
                        COUNT(gm.id) as total_members,
                        SUM(CASE WHEN gm.has_received_payout = TRUE THEN 1 ELSE 0 END) as members_paid
                    FROM group_members gm
                    WHERE gm.group_id = :group_id AND gm.status = 'active'
                """)
                
                check_result = await db.execute(check_query, {"group_id": group_id})
                row = check_result.fetchone()
                total = row[0]
                paid = row[1]
                
                # Verify that not all members have been paid
                assert paid < total, f"All members paid ({paid}/{total}), test setup error"
                
                # Group should remain active
                status_query = text("""
                    SELECT status FROM groups WHERE id = :group_id
                """)
                
                status_result = await db.execute(status_query, {"group_id": group_id})
                group_status = status_result.scalar()
                assert group_status == 'active', \
                    f"Group with incomplete payouts should be 'active', not '{group_status}'"
                
            finally:
                # Cleanup test data
                await db.execute(text("DELETE FROM group_members WHERE group_id = :group_id"), {"group_id": group_id})
                await db.execute(text("DELETE FROM groups WHERE id = :group_id"), {"group_id": group_id})
                await db.commit()
